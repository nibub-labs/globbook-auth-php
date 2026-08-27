<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use Globbook\Auth\Client;
use PHPUnit\Framework\TestCase;

final class ParseCallbackParamsTest extends TestCase
{
    public function testParsesCode(): void
    {
        $result = Client::parseCallbackParams(['code' => 'xyz789']);

        self::assertSame('xyz789', $result->code);
    }

    public function testReturnsNullWhenParamNotPresent(): void
    {
        $result = Client::parseCallbackParams(['some_other_param' => 'value']);

        self::assertNull($result->code);
    }

    public function testReturnsNullForEmptyArray(): void
    {
        $result = Client::parseCallbackParams([]);

        self::assertNull($result->code);
    }

    public function testEmptyStringCodeReturnsNull(): void
    {
        $result = Client::parseCallbackParams(['code' => '']);

        self::assertNull($result->code);
    }

    public function testParsesState(): void
    {
        $result = Client::parseCallbackParams(['code' => 'xyz789', 'state' => 'csrf-token-123']);

        self::assertSame('csrf-token-123', $result->state);
    }

    public function testStateIsNullWhenNotPresent(): void
    {
        $result = Client::parseCallbackParams(['code' => 'xyz789']);

        self::assertNull($result->state);
    }
}
