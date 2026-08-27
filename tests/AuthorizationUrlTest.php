<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Client;
use Globbook\Auth\Config;
use Globbook\Auth\Scope;
use PHPUnit\Framework\TestCase;

final class AuthorizationUrlTest extends TestCase
{
    public function testBuildsCorrectUrlAgainstDefaultBaseUrl(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        self::assertSame(
            'https://globbook.com/api/v2/oauth/authorize?client_id=my-client-id',
            $client->getAuthorizationUrl(),
        );
    }

    public function testBuildsCorrectUrlAgainstCustomBaseUrl(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(
                clientId: 'my-client-id',
                clientSecret: 'secret',
                redirectUrl: 'https://example.com/callback',
                baseUrl: 'https://staging.globbook.com',
            ),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        self::assertSame(
            'https://staging.globbook.com/api/v2/oauth/authorize?client_id=my-client-id',
            $client->getAuthorizationUrl(),
        );
    }

    public function testClientIdIsUrlEncoded(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'id with spaces&stuff', clientSecret: 'secret', redirectUrl: 'https://example.com/cb'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        $url = $client->getAuthorizationUrl();

        self::assertStringContainsString('client_id=id+with+spaces%26stuff', $url);
    }

    public function testScopesAreAppendedAsSpaceDelimitedParam(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        $url = $client->getAuthorizationUrl([Scope::BIRTHDATE, Scope::GENDER]);

        self::assertSame(
            'https://globbook.com/api/v2/oauth/authorize?client_id=my-client-id&scope=birthdate+gender',
            $url,
        );
    }

    public function testNoScopesMatchesBaseUrl(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        self::assertSame($client->getAuthorizationUrl(), $client->getAuthorizationUrl([]));
    }

    public function testStateIsAppendedAsParam(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        $url = $client->getAuthorizationUrl(state: 'csrf-token-123');

        self::assertSame(
            'https://globbook.com/api/v2/oauth/authorize?client_id=my-client-id&state=csrf-token-123',
            $url,
        );
    }

    public function testScopesAndStateCanBeCombined(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        $url = $client->getAuthorizationUrl([Scope::BIRTHDATE, Scope::GENDER], 'csrf-token-123');

        self::assertSame(
            'https://globbook.com/api/v2/oauth/authorize?client_id=my-client-id&scope=birthdate+gender&state=csrf-token-123',
            $url,
        );
    }

    public function testNullStateMatchesBaseUrl(): void
    {
        $mockHttp = new MockHttpClient();
        $client = new Client(
            new Config(clientId: 'my-client-id', clientSecret: 'secret', redirectUrl: 'https://example.com/callback'),
            $mockHttp,
            MockHttpClient::requestFactory(),
            MockHttpClient::streamFactory(),
        );

        self::assertSame($client->getAuthorizationUrl(), $client->getAuthorizationUrl(state: null));
    }
}
