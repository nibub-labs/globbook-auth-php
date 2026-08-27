<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Client;
use Globbook\Auth\Config;
use Globbook\Auth\GlobbookAuthException;
use PHPUnit\Framework\TestCase;

final class GetUserInfoTest extends TestCase
{
    private function makeClient(MockHttpClient $mockHttp): Client
    {
        $config = new Config(
            clientId: 'my-client-id',
            clientSecret: 'my-client-secret',
            redirectUrl: 'https://example.com/callback',
        );

        return new Client($config, $mockHttp, MockHttpClient::requestFactory(), MockHttpClient::streamFactory());
    }

    private static function fullUserInfoResponse(): array
    {
        return [
            'sub' => 'md5hash123',
            'preferred_username' => 'janedoe',
            'profile_verified' => true,
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
            'given_name' => 'Jane',
            'family_name' => 'Doe',
            'bio' => 'Hello world',
            'picture' => 'https://cdn.globbook.com/avatar.jpg',
            'cover_image' => null,
            'website' => 'https://jane.example.com',
            'birthdate' => '1990-01-01',
            'gender' => 'female',
        ];
    }

    public function testSuccessfulFetchMapsAllTopLevelFields(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(200, json_encode(self::fullUserInfoResponse(), JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);
        $userInfo = $client->getUserInfo('valid-access-token');

        self::assertSame('md5hash123', $userInfo->sub);
        self::assertSame('janedoe', $userInfo->preferredUsername);
        self::assertTrue($userInfo->profileVerified);
        self::assertSame('jane@example.com', $userInfo->email);
        self::assertSame('Jane Doe', $userInfo->name);
        self::assertSame('Jane', $userInfo->givenName);
        self::assertSame('Doe', $userInfo->familyName);
        self::assertSame('Hello world', $userInfo->bio);
        self::assertSame('https://cdn.globbook.com/avatar.jpg', $userInfo->picture);
        self::assertNull($userInfo->coverImage);
        self::assertSame('https://jane.example.com', $userInfo->website);
        self::assertSame('1990-01-01', $userInfo->birthdate);
        self::assertSame('female', $userInfo->gender);
    }

    public function testSendsBearerAuthorizationHeaderAndGetMethod(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(200, json_encode(self::fullUserInfoResponse(), JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);
        $client->getUserInfo('my-token-value');

        $request = $mockHttp->lastRequest;
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://globbook.com/api/v2/oauth/userinfo', (string) $request->getUri());
        self::assertSame('Bearer my-token-value', $request->getHeaderLine('Authorization'));
    }

    public function testNullAccessTokenThrowsInvalidRequestWithoutMakingRequest(): void
    {
        $mockHttp = new MockHttpClient();
        $client = $this->makeClient($mockHttp);

        try {
            $client->getUserInfo(null);
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }

        self::assertNull($mockHttp->lastRequest);
    }

    public function testInvalidTokenErrorIsWrapped(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(401, json_encode([
            'error' => 'invalid_token',
            'error_description' => 'The access token is expired.',
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);

        try {
            $client->getUserInfo('expired-token');
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('invalid_token', $e->errorCode);
            self::assertSame('The access token is expired.', $e->errorDescription);
            self::assertSame(401, $e->statusCode);
        }
    }
}
