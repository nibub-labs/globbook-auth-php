<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * Configuration accepted by the {@see Client} constructor.
 *
 * All three of `clientId`, `clientSecret`, and `redirectUrl` are required and
 * are validated synchronously in the constructor — construction throws
 * {@see \InvalidArgumentException} immediately if any is missing or blank,
 * rather than deferring the failure to the first API call. This mirrors the
 * sibling JS (`GlobbookAuthConfig`) and Go (`Config`) SDKs' fail-fast
 * behavior exactly.
 */
final class Config
{
    /** Production Globbook API origin, used when `baseUrl` is not supplied. */
    public const DEFAULT_BASE_URL = 'https://globbook.com';

    /** Normalized base URL (trailing slashes stripped). */
    public readonly string $baseUrl;

    /**
     * @param string $clientId     Your app's client ID, issued when you register the app in
     *                              the Globbook developer console. Sent as `client_id` on every
     *                              request.
     * @param string $clientSecret Your app's client secret, issued alongside `clientId`.
     *
     *                              SECURITY: this value must only ever be used in server-side
     *                              PHP code (which all PHP code inherently is) — but never log
     *                              it, never expose it via an API response, and never reuse a
     *                              server-side `Config` in a context (e.g. a BFF endpoint that
     *                              echoes config to a browser) that could leak it to a client.
     *                              See the README's "Security" section.
     * @param string $redirectUrl  The URL Globbook redirects the user's browser back to after
     *                              they approve (or deny) the consent screen. Must exactly match
     *                              the redirect URL registered for this app in the Globbook
     *                              developer console — this SDK does not validate that match
     *                              itself, the backend does.
     * @param string $baseUrl      Base URL of the Globbook API. Defaults to
     *                              {@see Config::DEFAULT_BASE_URL}. Override this to point at a
     *                              staging/self-hosted environment.
     *
     * @throws \InvalidArgumentException if `clientId`, `clientSecret`, or `redirectUrl` is
     *                                    empty/blank.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUrl,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        self::assertNonEmpty($clientId, 'clientId');
        self::assertNonEmpty($clientSecret, 'clientSecret');
        self::assertNonEmpty($redirectUrl, 'redirectUrl');
        self::assertNonEmpty($baseUrl, 'baseUrl');

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Redacts the client secret from `var_dump()`/`var_export()`-style output so an accidental
     * dump of a `Config` (e.g. in a debug log) never leaks it.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '[REDACTED]',
            'redirectUrl' => $this->redirectUrl,
            'baseUrl' => $this->baseUrl,
        ];
    }

    private static function assertNonEmpty(string $value, string $name): void
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(
                sprintf('Globbook\\Auth\\Config: "%s" is required and must be a non-empty string.', $name),
            );
        }
    }
}
