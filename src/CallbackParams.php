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
     * @param string|null $code The authorization code to pass to
     *                            {@see Client::exchangeCodeForToken()}. `null` if `code` was not
     *                            present in the query parameters.
     */
    public function __construct(
        public readonly ?string $code,
    ) {
    }
}
