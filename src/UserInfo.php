<?php

declare(strict_types=1);

namespace Globbook\Auth;

/**
 * The authenticated user's profile, as returned by `GET /api/v2/oauth/userinfo`.
 *
 * Properties mirror the OIDC (OpenID Connect) convention (`sub`, `preferredUsername`,
 * `givenName`, ...).
 */
final class UserInfo
{
    /**
     * @param string      $sub               OIDC subject identifier — an MD5 hash, not the raw
     *                                         numeric Globbook user id, but stable per-user.
     * @param string      $preferredUsername The user's @handle/username.
     * @param bool        $profileVerified   Whether the user's profile is verified (blue-check
     *                                         equivalent).
     * @param string      $email             The user's email address.
     * @param string      $name              First + last name joined by a space, or just one
     *                                         half if the other is empty.
     * @param string      $givenName         The user's first name.
     * @param string      $familyName        The user's last name.
     * @param string      $bio               The user's bio text, or `''` if unset.
     * @param string|null $picture           Signed CDN URL, or `null` if the user has no avatar.
     * @param string|null $coverImage        Signed CDN URL, or `null` if the user has no cover
     *                                         image.
     * @param string      $website           The user's website URL, or `''` if unset.
     * @param string|null $birthdate         Restricted claim, `YYYY-MM-DD`. `null` unless your
     *                                         app is verified in the Globbook Developer Console
     *                                         AND the user granted the `birthdate` scope at
     *                                         consent time — see {@see Client::getAuthorizationUrl()}.
     * @param string|null $gender            Restricted claim, the user's gender. Same
     *                                         verified+granted-scope gating as `$birthdate`
     *                                         (scope `gender`).
     * @param string|null $phoneNumber       Restricted claim, the user's phone number. Same
     *                                         gating as `$birthdate` (scope `phone`).
     * @param string|null $address           Restricted claim, `"city country"` — this platform
     *                                         stores no street-level address. Same gating as
     *                                         `$birthdate` (scope `address`).
     */
    public function __construct(
        public readonly string $sub,
        public readonly string $preferredUsername,
        public readonly bool $profileVerified,
        public readonly string $email,
        public readonly string $name,
        public readonly string $givenName,
        public readonly string $familyName,
        public readonly string $bio,
        public readonly ?string $picture,
        public readonly ?string $coverImage,
        public readonly string $website,
        public readonly ?string $birthdate = null,
        public readonly ?string $gender = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $address = null,
    ) {
    }

    /**
     * Builds a {@see UserInfo} from the raw decoded JSON body of a successful
     * `GET /api/v2/oauth/userinfo` response.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            sub: (string) ($raw['sub'] ?? ''),
            preferredUsername: (string) ($raw['preferred_username'] ?? ''),
            profileVerified: (bool) ($raw['profile_verified'] ?? false),
            email: (string) ($raw['email'] ?? ''),
            name: (string) ($raw['name'] ?? ''),
            givenName: (string) ($raw['given_name'] ?? ''),
            familyName: (string) ($raw['family_name'] ?? ''),
            bio: (string) ($raw['bio'] ?? ''),
            picture: isset($raw['picture']) ? (string) $raw['picture'] : null,
            coverImage: isset($raw['cover_image']) ? (string) $raw['cover_image'] : null,
            website: (string) ($raw['website'] ?? ''),
            // Restricted claims are omitted from the response entirely (not sent as empty
            // strings) unless the app is verified and the user granted the matching scope —
            // preserve that as null rather than coercing a missing key to "".
            birthdate: isset($raw['birthdate']) ? (string) $raw['birthdate'] : null,
            gender: isset($raw['gender']) ? (string) $raw['gender'] : null,
            phoneNumber: isset($raw['phone_number']) ? (string) $raw['phone_number'] : null,
            address: isset($raw['address']) ? (string) $raw['address'] : null,
        );
    }
}
