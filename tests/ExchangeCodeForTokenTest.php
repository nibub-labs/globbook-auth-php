<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Client;
use Globbook\Auth\Config;
use Globbook\Auth\GlobbookAuthException;
use PHPUnit\Framework\TestCase;

final class ExchangeCodeForTokenTest extends TestCase
{
    private function makeClient(MockHttpClient $mockHttp, ?string $baseUrl = null): Client
    {
        $config = new Config(
            clientId: 'my-client-id',
            clientSecret: 'my-client-secret',
            redirectUrl: 'https://example.com/callback',
            baseUrl: $baseUrl ?? Config::DEFAULT_BASE_URL,
        );

        return new Client($config, $mockHttp, MockHttpClient::requestFactory(), MockHttpClient::streamFactory());
    }

    public function testSuccessfulExchangeReturnsTokenResponse(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(200, json_encode([
            'access_token' => 'the-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);
        $token = $client->exchangeCodeForToken('auth-code-value');

        self::assertSame('the-access-token', $token->accessToken);
        self::assertSame('Bearer', $token->tokenType);
        self::assertSame(3600, $token->expiresIn);
    }

    public function testSendsCorrectMethodUrlContentTypeAndBody(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(200, json_encode([
            'access_token' => 'tok',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);
        $client->exchangeCodeForToken('the-code');

        $request = $mockHttp->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://globbook.com/api/v2/oauth/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $bodyParams);
        self::assertSame('my-client-id', $bodyParams['client_id']);
        self::assertSame('my-client-secret', $bodyParams['client_secret']);
        self::assertSame('the-code', $bodyParams['code']);
    }

    public function testNullCodeThrowsInvalidRequestWithoutMakingRequest(): void
    {
        $mockHttp = new MockHttpClient();
        $client = $this->makeClient($mockHttp);

        try {
            $client->exchangeCodeForToken(null);
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('invalid_request', $e->errorCode);
        }

        self::assertNull($mockHttp->lastRequest, 'No HTTP request should have been made for an empty code.');
    }

    public function testEmptyStringCodeThrowsInvalidRequest(): void
    {
        $mockHttp = new MockHttpClient();
        $client = $this->makeClient($mockHttp);

        $this->expectException(GlobbookAuthException::class);
        $client->exchangeCodeForToken('   ');
    }

    public function testInvalidGrantErrorIsWrapped(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(401, json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code has expired.',
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);

        try {
            $client->exchangeCodeForToken('expired-code');
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('invalid_grant', $e->errorCode);
            self::assertSame('The authorization code has expired.', $e->errorDescription);
            self::assertSame(401, $e->statusCode);
        }
    }

    public function testInvalidRequestErrorIsWrapped(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(400, json_encode([
            'error' => 'invalid_request',
            'error_description' => 'Missing required field: code',
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);

        $this->expectException(GlobbookAuthException::class);
        $client->exchangeCodeForToken('some-code');
    }

    public function testNonJsonErrorBodyIsWrappedAsInvalidResponse(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(500, '<html>Internal Server Error</html>');

        $client = $this->makeClient($mockHttp);

        try {
            $client->exchangeCodeForToken('some-code');
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('invalid_response', $e->errorCode);
            self::assertSame(500, $e->statusCode);
        }
    }

    public function testNetworkFailureIsWrappedAsNetworkError(): void
    {
        $mockHttp = new MockHttpClient();
        $dummyRequest = MockHttpClient::requestFactory()->createRequest('POST', 'https://globbook.com/api/v2/oauth/token');
        $mockHttp->willThrow(new MockNetworkException('Connection refused', $dummyRequest));

        $client = $this->makeClient($mockHttp);

        try {
            $client->exchangeCodeForToken('some-code');
            self::fail('Expected GlobbookAuthException to be thrown.');
        } catch (GlobbookAuthException $e) {
            self::assertSame('network_error', $e->errorCode);
            self::assertStringContainsString('Connection refused', $e->errorDescription);
        }
    }

    public function testDebugInfoRedactsAccessToken(): void
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->willRespondWith(200, json_encode([
            'access_token' => 'super-secret-token-value',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));

        $client = $this->makeClient($mockHttp);
        $token = $client->exchangeCodeForToken('code');

        $dumped = print_r($token, true);

        self::assertStringNotContainsString('super-secret-token-value', $dumped);
    }
}
