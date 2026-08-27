<?php

declare(strict_types=1);

namespace Globbook\Auth;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Server-side client for "Sign in with Globbook" OAuth 2.0.
 *
 * Typical flow:
 * 1. Build a redirect URL with {@see Client::getAuthorizationUrl()} and send the user's browser
 *    there.
 * 2. Globbook redirects back to your `redirectUrl` with `?code=...` — parse it with the static
 *    {@see Client::parseCallbackParams()}.
 * 3. Exchange that code for an access token with {@see Client::exchangeCodeForToken()}.
 * 4. Fetch the user's profile with {@see Client::getUserInfo()}.
 *
 * @example
 * ```php
 * $client = new \Globbook\Auth\Client(new \Globbook\Auth\Config(
 *     clientId: getenv('GLOBBOOK_CLIENT_ID'),
 *     clientSecret: getenv('GLOBBOOK_CLIENT_SECRET'),
 *     redirectUrl: 'https://yourapp.com/auth/globbook/callback',
 * ));
 *
 * // Step 1 — redirect the browser
 * header('Location: ' . $client->getAuthorizationUrl());
 * exit;
 *
 * // Step 2/3/4 — in your callback route
 * $params = \Globbook\Auth\Client::parseCallbackParams($_GET);
 * $token = $client->exchangeCodeForToken($params->code);
 * $userInfo = $client->getUserInfo($token->accessToken);
 * ```
 *
 * SECURITY: only ever construct this class in server-side code. It requires `clientSecret`,
 * which must never be shipped to a browser bundle or an untrusted client.
 *
 * This client is stateless and safe to reuse across multiple requests/users — it holds no
 * per-user mutable state after construction.
 */
final class Client
{
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    /**
     * @param Config                       $config         Validated configuration — see
     *                                                       {@see Config}. Its constructor
     *                                                       already fails fast on missing
     *                                                       required fields, so no further
     *                                                       validation happens here.
     * @param ClientInterface|null         $httpClient     A PSR-18 HTTP client to use for token
     *                                                       exchange and userinfo requests. When
     *                                                       omitted, this SDK constructs a
     *                                                       default Guzzle-backed client — the
     *                                                       `guzzlehttp/guzzle` package must be
     *                                                       installed for that fallback to work
     *                                                       (it is a suggested/dev dependency of
     *                                                       this package; require it directly in
     *                                                       your own application if you don't
     *                                                       supply your own PSR-18 client).
     * @param RequestFactoryInterface|null $requestFactory A PSR-17 request factory. Defaults to
     *                                                       Guzzle's factory alongside the
     *                                                       default HTTP client.
     * @param StreamFactoryInterface|null  $streamFactory  A PSR-17 stream factory. Defaults to
     *                                                       Guzzle's factory alongside the
     *                                                       default HTTP client.
     *
     * @throws \RuntimeException if no `$httpClient`/`$requestFactory`/`$streamFactory` is
     *                             supplied and `guzzlehttp/guzzle` is not installed.
     */
    public function __construct(
        private readonly Config $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($httpClient !== null && $requestFactory !== null && $streamFactory !== null) {
            $this->httpClient = $httpClient;
            $this->requestFactory = $requestFactory;
            $this->streamFactory = $streamFactory;

            return;
        }

        [$defaultClient, $defaultRequestFactory, $defaultStreamFactory] = $this->createDefaultHttpStack();

        $this->httpClient = $httpClient ?? $defaultClient;
        $this->requestFactory = $requestFactory ?? $defaultRequestFactory;
        $this->streamFactory = $streamFactory ?? $defaultStreamFactory;
    }

    /**
     * Builds the URL to redirect the user's browser to for the Globbook-hosted consent screen.
     * This does not make a network request — the SDK's role here is purely to construct the
     * correct URL; your application is responsible for actually redirecting the browser (e.g.
     * `header('Location: ' . $url); exit;`).
     *
     * After the user approves, Globbook redirects back to the `redirectUrl` this client was
     * configured with, appending `?code=...`.
     *
     * @param list<string> $scopes Restricted scopes to request in addition to the base profile
     *                              — see {@see Scope} for the valid values (`Scope::BIRTHDATE`,
     *                              `Scope::GENDER`, `Scope::PHONE`, `Scope::ADDRESS`). Rendered
     *                              as a space-delimited `scope` query parameter. Requesting a
     *                              scope only has an effect if this app is verified in the
     *                              Globbook Developer Console — Globbook's consent screen never
     *                              offers restricted scopes to an unverified app, and
     *                              {@see Client::getUserInfo()} never returns them either way
     *                              unless the user actually grants them at consent time. Pass an
     *                              empty array (the default) for the base profile only.
     * @param string|null   $state  An opaque value you generate before redirecting the user here
     *                              — Globbook echoes it back unchanged in the `state` query
     *                              parameter on the redirect to your `redirectUrl`, so
     *                              {@see Client::parseCallbackParams()} can hand it back to you to
     *                              compare against what you stored before the redirect (RFC 6749
     *                              §10.12 CSRF protection). Globbook never interprets this value
     *                              itself. Optional; omit (`null`) to skip CSRF protection.
     *
     * @return string The full authorization URL, e.g.
     *                 `https://globbook.com/api/v2/oauth/authorize?client_id=...`.
     */
    public function getAuthorizationUrl(array $scopes = [], ?string $state = null): string
    {
        $params = ['client_id' => $this->config->clientId];
        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }
        if ($state !== null && $state !== '') {
            $params['state'] = $state;
        }

        return $this->config->baseUrl . '/api/v2/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Parses the `code` (and `state`, if present) query parameters out of the callback request
     * your app receives after the user approves consent. Framework-agnostic — accepts any
     * associative array of query parameters, so it works directly with PHP's own `$_GET`, or with
     * any framework's parsed query-parameter bag (e.g. Symfony's `$request->query->all()`, a
     * PSR-7 `UriInterface`'s query string run through `parse_str()`).
     *
     * @param array<string, mixed> $queryParams Typically `$_GET`, or an equivalent
     *                                            associative array of query parameters.
     *
     * @return CallbackParams `code`/`state` are `null` if not present. If you passed `$state` to
     *                         {@see Client::getAuthorizationUrl()}, compare the returned `state`
     *                         against what you stored before redirecting and reject the callback
     *                         on a mismatch — see the README's "CSRF protection (state)" section.
     *
     * @example
     * ```php
     * // Plain PHP
     * $params = Client::parseCallbackParams($_GET);
     *
     * // Symfony
     * $params = Client::parseCallbackParams($request->query->all());
     * ```
     */
    public static function parseCallbackParams(array $queryParams): CallbackParams
    {
        $code = $queryParams['code'] ?? null;
        $codeValue = ($code !== null && $code !== '') ? (string) $code : null;

        $state = $queryParams['state'] ?? null;
        $stateValue = ($state !== null && $state !== '') ? (string) $state : null;

        return new CallbackParams($codeValue, $stateValue);
    }

    /**
     * Exchanges an authorization code (from {@see Client::parseCallbackParams()}) for an access
     * token via `POST /api/v2/oauth/token`.
     *
     * Sent as `application/x-www-form-urlencoded` — the Globbook API rejects JSON bodies on this
     * endpoint with a 415. This is handled for you; you never need to set content type or encode
     * the body yourself.
     *
     * @param string|null $code The `code` value received in the OAuth callback (e.g.
     *                           `CallbackParams::$code`).
     *
     * @return TokenResponse The issued access token.
     *
     * @throws GlobbookAuthException if `code` is empty/null, the HTTP request itself fails
     *                                 (network error), or the API rejects the request (e.g.
     *                                 `invalid_grant` for an expired/already-used code).
     */
    public function exchangeCodeForToken(?string $code): TokenResponse
    {
        if ($code === null || trim($code) === '') {
            throw new GlobbookAuthException(
                'invalid_request',
                'exchangeCodeForToken: "code" is required and must be a non-empty string.',
            );
        }

        $formBody = http_build_query([
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'code' => $code,
        ]);

        $raw = $this->post('/api/v2/oauth/token', $formBody);

        return TokenResponse::fromArray($raw);
    }

    /**
     * Fetches the authenticated user's profile via `GET /api/v2/oauth/userinfo` using
     * `Authorization: Bearer {accessToken}`.
     *
     * @param string|null $accessToken The access token from
     *                                  {@see Client::exchangeCodeForToken()} (i.e.
     *                                  `TokenResponse::$accessToken`).
     *
     * @return UserInfo The user's profile.
     *
     * @throws GlobbookAuthException if `accessToken` is empty/null, the HTTP request itself
     *                                 fails (network error), or the API rejects it
     *                                 (`invalid_token` — missing, malformed, or expired).
     */
    public function getUserInfo(?string $accessToken): UserInfo
    {
        if ($accessToken === null || trim($accessToken) === '') {
            throw new GlobbookAuthException(
                'invalid_request',
                'getUserInfo: "accessToken" is required and must be a non-empty string.',
            );
        }

        $request = $this->requestFactory
            ->createRequest('GET', $this->config->baseUrl . '/api/v2/oauth/userinfo')
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->withHeader('Accept', 'application/json');

        $raw = $this->send($request);

        return UserInfo::fromArray($raw);
    }

    /**
     * Internal helper: POSTs a `application/x-www-form-urlencoded` body and returns the decoded
     * JSON on success, throwing {@see GlobbookAuthException} on any failure.
     *
     * @return array<string, mixed>
     */
    private function post(string $path, string $formBody): array
    {
        $request = $this->requestFactory
            ->createRequest('POST', $this->config->baseUrl . $path)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($formBody));

        return $this->send($request);
    }

    /**
     * Internal helper: sends a fully-built PSR-7 request through the configured PSR-18 client,
     * normalizing every failure mode (network error, non-JSON body, non-2xx status) into a
     * {@see GlobbookAuthException}.
     *
     * @return array<string, mixed>
     */
    private function send(RequestInterface $request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new GlobbookAuthException(
                'network_error',
                sprintf('Request to Globbook failed: %s', $e->getMessage()),
                null,
                $e,
            );
        }

        $status = $response->getStatusCode();
        $bodyContents = (string) $response->getBody();

        $decoded = json_decode($bodyContents, true);
        $isValidJson = json_last_error() === JSON_ERROR_NONE && is_array($decoded);

        if ($status < 200 || $status >= 300) {
            if ($isValidJson) {
                $errorCode = isset($decoded['error']) ? (string) $decoded['error'] : 'unknown_error';
                $errorDescription = isset($decoded['error_description'])
                    ? (string) $decoded['error_description']
                    : sprintf('Globbook API request failed with HTTP %d.', $status);

                throw new GlobbookAuthException($errorCode, $errorDescription, $status);
            }

            throw new GlobbookAuthException(
                'invalid_response',
                sprintf('Globbook API returned a non-JSON error response (HTTP %d).', $status),
                $status,
            );
        }

        if (!$isValidJson) {
            throw new GlobbookAuthException(
                'invalid_response',
                'Globbook API returned a non-JSON response.',
                $status,
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Constructs a default PSR-18/PSR-17 HTTP stack backed by Guzzle, used when the caller does
     * not supply their own PSR-18 client. Requires `guzzlehttp/guzzle` to be installed. The
     * Guzzle client is configured with `Config::$requestTimeoutSeconds` — this only applies to
     * this built-in default; a caller-supplied PSR-18 client controls its own timeout.
     *
     * @return array{0: ClientInterface, 1: RequestFactoryInterface, 2: StreamFactoryInterface}
     *
     * @throws \RuntimeException if `guzzlehttp/guzzle` is not installed.
     */
    private function createDefaultHttpStack(): array
    {
        if (!class_exists(\GuzzleHttp\Client::class) || !class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            throw new \RuntimeException(
                'Globbook\\Auth\\Client: no PSR-18 HTTP client was supplied, and the default '
                . '"guzzlehttp/guzzle" fallback is not installed. Either run '
                . '"composer require guzzlehttp/guzzle", or pass your own PSR-18 client, PSR-17 '
                . 'request factory, and PSR-17 stream factory to the Client constructor.',
            );
        }

        $guzzleClient = new \GuzzleHttp\Client([
            'timeout' => max(0.0, $this->config->requestTimeoutSeconds),
        ]);
        $factory = new \GuzzleHttp\Psr7\HttpFactory();

        return [$guzzleClient, $factory, $factory];
    }
}
