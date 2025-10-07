<?php
declare(strict_types=1);

namespace AntonBrutto\McpClientTests;

use AntonBrutto\McpClient\Internal\Deferred;
use AntonBrutto\McpCore\CancellationException;
use AntonBrutto\McpCore\CancellationToken;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TimeoutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeferredTest extends TestCase
{
    public function testResolveReturnsMessage(): void
    {
        $deferred = new Deferred('1', 'tools/list', CancellationToken::none(), null);
        $message = new JsonRpcMessage('1', 'tools/list', [], ['tools' => []]);

        $deferred->resolve($message);

        self::assertTrue($deferred->isSettled());
        self::assertSame($message, $deferred->message());
    }

    public function testRejectThrowsStoredException(): void
    {
        $deferred = new Deferred('2', 'tools/call', CancellationToken::none(), null);
        $error = new RuntimeException('boom');

        $deferred->reject($error);

        self::assertTrue($deferred->isSettled());

        $this->expectExceptionObject($error);
        $deferred->message();
    }

    public function testGuardCancelsOnToken(): void
    {
        $token = CancellationToken::none();
        $token->cancel();

        $flag = false;
        $deferred = new Deferred('3', 'tools/list', $token, null, function () use (&$flag): void {
            $flag = true;
        });

        $deferred->guard();

        self::assertTrue($deferred->isSettled());
        self::assertTrue($flag);

        $this->expectException(CancellationException::class);
        $deferred->message();
    }

    public function testGuardTimesOut(): void
    {
        $flag = false;
        $deferred = new Deferred('4', 'tools/list', CancellationToken::none(), microtime(true) - 1.0, function () use (&$flag): void {
            $flag = true;
        });

        $deferred->guard();

        self::assertTrue($deferred->isSettled());
        self::assertTrue($flag);

        $this->expectException(TimeoutException::class);
        $deferred->message();
    }

    public function testGuardDoesNotCancelTwice(): void
    {
        $calls = 0;
        $token = CancellationToken::none();
        $deferred = new Deferred('5', 'tools/list', $token, microtime(true) - 1.0, function () use (&$calls, $token): void {
            $calls++;
            $token->cancel();
        });

        $deferred->guard();

        $token->cancel();
        $deferred->guard();

        try {
            $deferred->message();
        } catch (TimeoutException|CancellationException $e) {
            // expected
        }

        self::assertSame(1, $calls);
    }
}
