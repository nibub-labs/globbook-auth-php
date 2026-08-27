<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * Restricted OIDC-style scopes you may pass to {@see Client::getAuthorizationUrl()} to request
 * restricted userinfo claims. Requesting a scope only has an effect if your app has been
 * verified in the Globbook Developer Console — an unverified app's consent screen never offers
 * these regardless of what's requested, and {@see Client::getUserInfo()} never returns them
 * either way unless the user actually grants them at consent time.
 */
final class Scope
{
    public const BIRTHDATE = 'birthdate';
    public const GENDER = 'gender';
    public const PHONE = 'phone';
    public const ADDRESS = 'address';

    private function __construct()
    {
    }
}
