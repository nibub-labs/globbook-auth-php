<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * Thrown for every API-level failure raised by the Globbook OAuth endpoints (token exchange,
 * userinfo). Wraps the OAuth-standard `{ error, error_description }` JSON body the backend
 * returns on non-2xx responses.
 *
 * The SDK never lets a raw PSR-18/PSR-7 exception or response leak to the caller for an
 * API-level failure — every failure path (network error, non-JSON body, malformed response, and
 * genuine OAuth errors) is normalized into this type, so calling code only ever needs to catch
 * one exception class.
 *
 * Well-known `errorCode` values returned by the Globbook OAuth API:
 * - `invalid_request` — a required field was missing or malformed in the request (e.g. a token
 *   exchange call missing `code`).
 * - `invalid_grant` — the `client_id`/`client_secret`/`code` combination was rejected (wrong
 *   secret, expired code, already-used code, etc.).
 * - `invalid_token` — the access token supplied to `/oauth/userinfo` is missing, malformed, or
 *   expired.
 * - `unsupported_media_type` — the request body was not sent as
 *   `application/x-www-form-urlencoded`. You should never see this from the SDK itself (it
 *   always sends the correct content type), but it's included in case a custom PSR-18 client
 *   implementation rewrites the request.
 * - `network_error` — the HTTP request itself failed (DNS, TLS, connection refused, timeout),
 *   surfaced by this SDK rather than by the Globbook API.
 * - `invalid_response` — the API returned a non-JSON or unexpectedly-shaped body.
 *
 * @example
 * ```php
 * try {
 *     $token = $client->exchangeCodeForToken($params->code);
 * } catch (GlobbookAuthException $e) {
 *     error_log("Globbook auth failed: {$e->errorCode} — {$e->errorDescription}");
 * }
 * ```
 */
final class GlobbookAuthException extends \RuntimeException
{
    /**
     * @param string      $errorCode        The OAuth error code (e.g. `invalid_grant`).
     * @param string      $errorDescription Human-readable description of the error, as returned
     *                                       by the API (or synthesized by this SDK for
     *                                       network/decoding failures) — safe to show in logs.
     * @param int|null    $statusCode       The HTTP status code of the response that produced
     *                                       this error, if known (`null` for a pure network
     *                                       failure that never received a response).
     * @param \Throwable|null $previous     The underlying exception that caused this one (e.g. a
     *                                       PSR-18 `ClientExceptionInterface`), if any.
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly string $errorDescription,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        // The exception message intentionally mirrors errorDescription only — never include
        // request bodies/headers here, since callers sometimes let SDK exceptions bubble into
        // logs and we must never leak a client_secret or access_token that way.
        parent::__construct($errorDescription !== '' ? $errorDescription : $errorCode, 0, $previous);
    }

    /**
     * Redacts nothing sensitive by default (this exception never carries secrets), but defined
     * explicitly so a `var_dump()` of this exception has a stable, intentional shape rather than
     * dumping the full internal `\RuntimeException` trace/previous-exception chain, which could
     * itself contain a redacted-elsewhere secret from a lower layer.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'errorCode' => $this->errorCode,
            'errorDescription' => $this->errorDescription,
            'statusCode' => $this->statusCode,
        ];
    }
}
