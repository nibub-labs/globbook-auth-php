<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testValidConfigConstructsSuccessfully(): void
    {
        $config = new Config(
            clientId: 'client-123',
            clientSecret: 'secret-abc',
            redirectUrl: 'https://example.com/callback',
        );

        self::assertSame('client-123', $config->clientId);
        self::assertSame('secret-abc', $config->clientSecret);
        self::assertSame('https://example.com/callback', $config->redirectUrl);
        self::assertSame(Config::DEFAULT_BASE_URL, $config->baseUrl);
    }

    public function testCustomBaseUrlIsNormalizedByStrippingTrailingSlash(): void
    {
        $config = new Config(
            clientId: 'client-123',
            clientSecret: 'secret-abc',
            redirectUrl: 'https://example.com/callback',
            baseUrl: 'https://staging.globbook.com/',
        );

        self::assertSame('https://staging.globbook.com', $config->baseUrl);
    }

    public function testEmptyClientIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/clientId/');

        new Config(clientId: '', clientSecret: 'secret-abc', redirectUrl: 'https://example.com/callback');
    }

    public function testBlankClientSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/clientSecret/');

        new Config(clientId: 'client-123', clientSecret: '   ', redirectUrl: 'https://example.com/callback');
    }

    public function testEmptyRedirectUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/redirectUrl/');

        new Config(clientId: 'client-123', clientSecret: 'secret-abc', redirectUrl: '');
    }

    public function testDebugInfoRedactsClientSecret(): void
    {
        $config = new Config(
            clientId: 'client-123',
            clientSecret: 'super-secret-value',
            redirectUrl: 'https://example.com/callback',
        );

        $dumped = print_r($config, true);

        self::assertStringNotContainsString('super-secret-value', $dumped);
        self::assertStringContainsString('[REDACTED]', $dumped);
    }
}
