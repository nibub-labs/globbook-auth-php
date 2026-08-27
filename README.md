# nibub-labs/globbook-auth

Official PHP SDK for **"Sign in with Globbook"** — OAuth 2.0 authorization code flow, token
exchange, and user profile retrieval, for server-side PHP applications.

This package wraps Globbook's OAuth 2.0 API (`/api/v2/oauth/*`) behind a small, modern, fully
typed PHP client. It implements the identical protocol as the official
[`globbook-auth-js`](https://github.com/nibub-labs/globbook-auth-js) and
[`globbook-auth-go`](https://github.com/nibub-labs/globbook-auth-go) SDKs — pick whichever matches
your stack.

- PHP 8.1+, `declare(strict_types=1)` everywhere, fully typed (no implicit `any`-equivalents).
- [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client abstraction — bring your own HTTP
  client (Guzzle, Symfony HttpClient, etc.), or let this package use a built-in Guzzle default.
- A single exception type (`GlobbookAuthException`) for every API-level failure.
- Readonly value objects for configuration and every response shape.
- Secrets (client secret, access token) are never exposed via `var_dump()`/`print_r()`.

## Installation

```bash
composer require nibub-labs/globbook-auth
```

This package depends only on the PSR-18/PSR-17 *interfaces* (`psr/http-client`,
`psr/http-factory`), not a concrete HTTP client. If your application doesn't already have a
PSR-18 client installed, also require Guzzle (the SDK's built-in default):

```bash
composer require guzzlehttp/guzzle
```

If you already use a PSR-18 client elsewhere in your app (Symfony HttpClient via
`symfony/http-client` + `nyholm/psr7`, etc.), you don't need Guzzle — just pass your existing
client into the `Client` constructor (see [Using a custom HTTP client](#using-a-custom-http-client)
below).

## Quickstart

A complete, framework-agnostic example of the full flow: redirect → callback → token exchange →
user info. This works as-is in plain PHP, and drops into Laravel/Symfony controllers, WordPress
plugins, or any other PHP server context without modification (this package makes no assumptions
about your framework).

**`login.php`** — starts the flow by redirecting to Globbook's hosted consent screen:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Globbook\Auth\Client;
use Globbook\Auth\Config;

$client = new Client(new Config(
    clientId: getenv('GLOBBOOK_CLIENT_ID'),
    clientSecret: getenv('GLOBBOOK_CLIENT_SECRET'),
    redirectUrl: 'https://yourapp.com/callback.php',
));

header('Location: ' . $client->getAuthorizationUrl());
exit;
```

**`callback.php`** — receives the redirect back from Globbook, exchanges the code for a token,
and fetches the user's profile:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Globbook\Auth\Client;
use Globbook\Auth\Config;
use Globbook\Auth\GlobbookAuthException;

$client = new Client(new Config(
    clientId: getenv('GLOBBOOK_CLIENT_ID'),
    clientSecret: getenv('GLOBBOOK_CLIENT_SECRET'),
    redirectUrl: 'https://yourapp.com/callback.php',
));

// Globbook redirects here with ?code=...
$params = Client::parseCallbackParams($_GET);

if ($params->code === null) {
    http_response_code(400);
    echo 'Sign-in was cancelled or the callback is missing its authorization code.';
    exit;
}

try {
    $token = $client->exchangeCodeForToken($params->code);
    $userInfo = $client->getUserInfo($token->accessToken);
} catch (GlobbookAuthException $e) {
    http_response_code(502);
    error_log(sprintf('Globbook sign-in failed: %s (%s)', $e->errorCode, $e->errorDescription));
    echo 'Sign-in failed. Please try again.';
    exit;
}

// $userInfo->sub is a stable per-user identifier (an MD5 hash) — use it as the foreign key
// linking your own user records to their Globbook account.
$_SESSION['user'] = [
    'globbook_sub' => $userInfo->sub,
    'username' => $userInfo->preferredUsername,
    'email' => $userInfo->email,
    'name' => $userInfo->name,
    'avatar' => $userInfo->picture,
];

header('Location: /dashboard');
exit;
```

## The flow

1. **Redirect** — `Client::getAuthorizationUrl()` builds the URL to Globbook's hosted consent
   page. Your app redirects the user's browser there; this method makes no network request
   itself.
2. **Callback** — after the user approves, Globbook redirects the browser back to your
   `redirectUrl` (exactly as registered in the Globbook developer console) with a `code` query
   parameter. `Client::parseCallbackParams()` parses it.
3. **Token exchange** — `Client::exchangeCodeForToken()` trades that code for an access token via
   `POST /api/v2/oauth/token`.
4. **User info** — `Client::getUserInfo()` fetches the authenticated user's profile via
   `GET /api/v2/oauth/userinfo`.

Registering your application (obtaining a `clientId`/`clientSecret` and registering your
`redirectUrl`) happens once, ahead of time, in Globbook's own developer console — this package
does not implement an app-registration API.

## API reference

### `Globbook\Auth\Config`

Immutable configuration for a `Client`.

```php
new Config(
    string $clientId,
    string $clientSecret,
    string $redirectUrl,
    string $baseUrl = Config::DEFAULT_BASE_URL, // "https://globbook.com"
)
```

| Property       | Type     | Description                                                                                     |
|----------------|----------|---------------------------------------------------------------------------------------------------|
| `$clientId`    | `string` | Your app's client ID, from the Globbook developer console.                                       |
| `$clientSecret`| `string` | Your app's client secret. **Server-side only** — see [Security](#security).                       |
| `$redirectUrl` | `string` | Must exactly match the redirect URL registered for this app.                                      |
| `$baseUrl`     | `string` | Globbook API origin. Defaults to `https://globbook.com`; override for staging/self-hosted setups. |

**Throws:** `\InvalidArgumentException` synchronously (in the constructor) if `clientId`,
`clientSecret`, or `redirectUrl` is empty or blank — configuration errors fail immediately at
construction, not on the first API call.

### `Globbook\Auth\Client`

```php
new Client(
    Config $config,
    ?Psr\Http\Client\ClientInterface $httpClient = null,
    ?Psr\Http\Message\RequestFactoryInterface $requestFactory = null,
    ?Psr\Http\Message\StreamFactoryInterface $streamFactory = null,
)
```

Pass all three PSR-18/PSR-17 arguments together to use your own HTTP stack (see
[Using a custom HTTP client](#using-a-custom-http-client)), or omit all three to use the built-in
Guzzle-backed default (requires `guzzlehttp/guzzle` to be installed).

#### `getAuthorizationUrl(): string`

Builds the URL to redirect the user's browser to for the Globbook-hosted consent screen. Pure URL
construction — makes no network request.

#### `static parseCallbackParams(array $queryParams): CallbackParams`

Extracts the `code` query parameter from a callback request. Pass `$_GET` directly, or any other
framework's equivalent associative array (e.g. Symfony's `$request->query->all()`).

Returns a `CallbackParams` with a single readonly property, `?string $code` (`null` if not
present).

#### `exchangeCodeForToken(?string $code): TokenResponse`

Exchanges an authorization code for an access token (`POST /api/v2/oauth/token`, sent as
`application/x-www-form-urlencoded`).

**Throws:** `GlobbookAuthException` if `$code` is `null`/empty, the request fails at the network
level, or the API rejects it (`invalid_grant` for an expired/reused code, `invalid_request` for a
malformed request).

#### `getUserInfo(?string $accessToken): UserInfo`

Fetches the authenticated user's profile (`GET /api/v2/oauth/userinfo`,
`Authorization: Bearer {accessToken}`).

**Throws:** `GlobbookAuthException` if `$accessToken` is `null`/empty, the request fails at the
network level, or the API rejects it (`invalid_token` for a missing/malformed/expired token).

### `Globbook\Auth\TokenResponse`

| Property        | Type     | Description                                              |
|------------------|----------|------------------------------------------------------------|
| `$accessToken`   | `string` | Bearer token — pass to `Client::getUserInfo()`.             |
| `$tokenType`     | `string` | Always `"Bearer"`.                                          |
| `$expiresIn`     | `int`    | Token lifetime in seconds from issuance (typically `3600`). |

### `Globbook\Auth\UserInfo`

OIDC-style properties:

| Property             | Type            | Description                                                     |
|----------------------|-----------------|-------------------------------------------------------------------|
| `$sub`                | `string`        | Stable subject identifier (MD5 hash) — use as your own foreign key.|
| `$preferredUsername`  | `string`        | The user's `@handle`.                                             |
| `$profileVerified`    | `bool`          | Whether the account is verified.                                  |
| `$email`              | `string`        | Email address.                                                    |
| `$name`               | `string`        | First + last name joined by a space (or just one half).           |
| `$givenName`          | `string`        | First name.                                                       |
| `$familyName`         | `string`        | Last name.                                                        |
| `$bio`                | `string`        | Bio text, `''` if unset.                                          |
| `$picture`            | `?string`       | Signed CDN avatar URL, or `null`.                                  |
| `$coverImage`         | `?string`       | Signed CDN cover URL, or `null`.                                   |
| `$website`            | `string`        | Website URL, `''` if unset.                                       |
| `$birthdate`          | `string`        | `YYYY-MM-DD`, or `''` if unset.                                    |
| `$gender`             | `string`        | Gender, `''` if unset.                                             |

### `Globbook\Auth\GlobbookAuthException`

Extends `\RuntimeException`. Thrown for every API-level failure — the SDK never lets a raw
PSR-18/PSR-7 exception escape from `exchangeCodeForToken()` or `getUserInfo()`.

| Property             | Type      | Description                                                              |
|----------------------|-----------|-----------------------------------------------------------------------------|
| `$errorCode`          | `string`  | Machine-readable OAuth error code (see below).                            |
| `$errorDescription`   | `string`  | Human-readable description — safe to log.                                 |
| `$statusCode`         | `?int`    | The HTTP status of the failing response, or `null` for a pure network error.|

Well-known `$errorCode` values:

| Code                       | Meaning                                                                                   |
|-----------------------------|---------------------------------------------------------------------------------------------|
| `invalid_request`           | A required field was missing/malformed (e.g. `exchangeCodeForToken(null)`).                 |
| `invalid_grant`              | `client_id`/`client_secret`/`code` combination rejected (wrong secret, expired/used code).   |
| `invalid_token`              | The access token given to `getUserInfo()` is missing, malformed, or expired.                 |
| `unsupported_media_type`     | The request wasn't sent as `application/x-www-form-urlencoded`. Should never occur from this SDK itself. |
| `network_error`              | The HTTP request itself failed (DNS, TLS, connection refused, timeout).                      |
| `invalid_response`           | Globbook returned a non-JSON or unexpectedly-shaped body.                                    |

## Error handling

```php
use Globbook\Auth\GlobbookAuthException;

try {
    $token = $client->exchangeCodeForToken($params->code);
    $userInfo = $client->getUserInfo($token->accessToken);
} catch (GlobbookAuthException $e) {
    match ($e->errorCode) {
        'invalid_grant' => /* the code was expired or already used — restart the flow */ null,
        'invalid_token' => /* the access token expired — restart the flow */ null,
        default => error_log("Globbook auth error [{$e->errorCode}]: {$e->errorDescription}"),
    };
}
```

Every failure path — a rejected OAuth request, a network failure, or a malformed/non-JSON
response — is normalized into `GlobbookAuthException`, so this is the only exception type you
need to catch around SDK calls.

## Using a custom HTTP client

By default, `Client` builds a Guzzle-backed PSR-18/PSR-17 stack for you (install
`guzzlehttp/guzzle` for this to work). To use a different PSR-18 client — Symfony HttpClient, a
client with custom timeouts/retries/logging middleware, or a test double — pass all three
arguments explicitly:

```php
use Globbook\Auth\Client;
use Globbook\Auth\Config;
use Symfony\Component\HttpClient\Psr18Client;

$psr18Client = new Psr18Client(); // implements ClientInterface, RequestFactoryInterface, and StreamFactoryInterface
$client = new Client(
    new Config(clientId: '...', clientSecret: '...', redirectUrl: '...'),
    $psr18Client,
    $psr18Client,
    $psr18Client,
);
```

## Security

- **`clientSecret` is a server-only credential.** Never construct a `Config` with a real
  `clientSecret` in any code path that could ship it to a browser or otherwise-untrusted client —
  a client-side template, an SPA bundle, or a "backend-for-frontend" endpoint that echoes
  configuration back to the browser. PHP itself always executes server-side, but that alone
  doesn't prevent a route from accidentally leaking configuration in a JSON response or rendered
  page — keep `clientSecret` (and the resulting access tokens) inside your backend only.
- Access tokens and the client secret are redacted from `var_dump()`/`print_r()` output on every
  class that carries one (`Config`, `TokenResponse`), via `__debugInfo()`.
- `GlobbookAuthException`'s message is built only from `errorDescription`/`errorCode` — it never
  includes request bodies or headers, so accidentally logging a caught exception cannot leak a
  secret.

## Development

```bash
composer install
composer test   # PHPUnit
composer stan    # PHPStan static analysis (level 8)
composer check   # both
```

## License

[MIT](./LICENSE) © Nibub
