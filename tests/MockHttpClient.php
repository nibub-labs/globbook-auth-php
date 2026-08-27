<?php

declare(strict_types=1);

namespace Globbook\Auth\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * A minimal, hand-rolled PSR-18 test double. Never makes a real network call — each test wires
 * up a canned response (or a thrown network exception) and asserts against the captured request.
 *
 * Uses Guzzle's PSR-7 `Response` class purely as a convenient concrete `ResponseInterface`
 * implementation (guzzlehttp/psr7 is a transitive dependency of guzzlehttp/guzzle, itself a
 * require-dev dependency of this package) — no real HTTP transport is involved.
 */
final class MockHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    private ?ResponseInterface $nextResponse = null;

    private ?ClientExceptionInterface $nextException = null;

    public function willRespondWith(int $status, string $jsonBody): void
    {
        $this->nextResponse = new Response($status, ['Content-Type' => 'application/json'], $jsonBody);
        $this->nextException = null;
    }

    public function willThrow(ClientExceptionInterface $exception): void
    {
        $this->nextException = $exception;
        $this->nextResponse = null;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->nextException !== null) {
            throw $this->nextException;
        }

        if ($this->nextResponse === null) {
            throw new \LogicException('MockHttpClient: no response/exception was queued before sendRequest() was called.');
        }

        return $this->nextResponse;
    }

    public static function requestFactory(): RequestFactoryInterface
    {
        return new HttpFactory();
    }

    public static function streamFactory(): StreamFactoryInterface
    {
        return new HttpFactory();
    }
}
