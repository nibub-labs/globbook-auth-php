# Changelog

All notable changes to this package are documented in this file.

## 1.0.0 - Initial release

- Initial release of the official PHP SDK for "Sign in with Globbook" OAuth 2.0.
- `Globbook\Auth\Client` — server-side OAuth client:
  - `getAuthorizationUrl()` — builds the hosted-consent redirect URL.
  - `Client::parseCallbackParams(array $queryParams)` — framework-agnostic parsing of the `code`
    callback query parameter.
  - `exchangeCodeForToken(?string $code)` — trades an authorization code for an access token via
    `POST /api/v2/oauth/token` (`application/x-www-form-urlencoded`).
  - `getUserInfo(?string $accessToken)` — fetches the authenticated user's profile via
    `GET /api/v2/oauth/userinfo`.
- PSR-18 (`psr/http-client`) + PSR-17 (`psr/http-factory`) HTTP abstraction, with an optional
  Guzzle-backed default so the client works out of the box when `guzzlehttp/guzzle` is installed.
- Typed, readonly value objects: `Config`, `TokenResponse`, `UserInfo`, `CallbackParams`.
- `GlobbookAuthException` — a single exception type for every API-level failure, carrying
  `errorCode`, `errorDescription`, and `statusCode`.
- Fail-fast `Config` validation (`\InvalidArgumentException` on missing/empty `clientId`,
  `clientSecret`, or `redirectUrl`).
- Secrets (client secret, access token) are redacted from `__debugInfo()` output on every class
  that carries one.
