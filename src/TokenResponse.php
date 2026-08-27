<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * The result of exchanging an authorization code for an access token
 * (`POST /api/v2/oauth/token`), translated from the API's snake_case JSON body into an
 * idiomatic, typed PHP value object.
 */
final class TokenResponse
{
    /**
     * @param string $accessToken Opaque bearer token — pass this to {@see Client::getUserInfo}.
     *                             Never log this value.
     * @param string $tokenType   Always `"Bearer"` for this API, but kept as a real field rather
     *                             than assumed.
     * @param int    $expiresIn   Token lifetime in seconds from the moment it was issued
     *                             (typically 3600).
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
    ) {
    }

    /**
     * Builds a {@see TokenResponse} from the raw decoded JSON body of a successful
     * `POST /api/v2/oauth/token` response.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            accessToken: (string) ($raw['access_token'] ?? ''),
            tokenType: (string) ($raw['token_type'] ?? ''),
            expiresIn: (int) ($raw['expires_in'] ?? 0),
        );
    }

    /**
     * Redacts the access token from `var_dump()`/`var_export()`-style output so an accidental
     * dump of a `TokenResponse` (e.g. in a debug log) never leaks it.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => '[REDACTED]',
            'tokenType' => $this->tokenType,
            'expiresIn' => $this->expiresIn,
        ];
    }
}
