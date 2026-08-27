<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Client;
use Globbook\Auth\Config;
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
}
