# Changelog

All notable changes to this package are documented in this file.

## 1.1.0

- **Fixed**: `UserInfo::$birthdate` and `UserInfo::$gender` are now `?string` instead of `string`,
  and default to `null`. Globbook's `/api/v2/oauth/userinfo` omits these fields entirely (not as
  empty strings) unless your app is verified in the Globbook Developer Console and the user
  granted the matching scope at consent time. If you compared either property to `''`, switch to
  a `null` check instead.
- **Added**: `UserInfo::$phoneNumber` and `UserInfo::$address` (`?string`) — restricted claims
  that were previously unreachable through this SDK entirely.
- **Added**: `Client::getAuthorizationUrl(array $scopes = [], ?string $state = null)` accepts an
  optional scopes array (see the new `Globbook\Auth\Scope` constants) to request restricted claims
  — previously there was no way to request these scopes at all, so `getUserInfo()` could never
  have returned them regardless of app verification status.
- **Added**: `getAuthorizationUrl()`'s `$state` argument and `CallbackParams::$state` — optional
  CSRF protection (RFC 6749 §10.12). Generate an unguessable value, pass it as `$state`, and
  compare `parseCallbackParams()`'s returned `$state` against it in your callback route before
  exchanging the code. Entirely opt-in; omitting it changes no other behavior. See the README's
  "CSRF protection (state)" section.
- **Added**: `Config::$requestTimeoutSeconds` (default `10.0`) bounds every request made by the
  *default* Guzzle-backed HTTP stack; ignored if you supply your own PSR-18 client.

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
