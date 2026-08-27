<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * Result of {@see Client::parseCallbackParams()} — the authorization code extracted from the
 * query parameters Globbook redirects the browser to after consent.
 */
final class CallbackParams
{
    /**
     * @param string|null $code  The authorization code to pass to
     *                            {@see Client::exchangeCodeForToken()}. `null` if `code` was not
     *                            present in the query parameters.
     * @param string|null $state The CSRF-protection value Globbook echoed back, if you passed one
     *                            to {@see Client::getAuthorizationUrl()}'s `$state` argument.
     *                            `null` if you didn't send one, or it wasn't present in the
     *                            callback query parameters. If you sent one, compare this against
     *                            what you stored before redirecting and reject the callback on a
     *                            mismatch — see the README's "CSRF protection (state)" section.
     */
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $state = null,
    ) {
    }
}
