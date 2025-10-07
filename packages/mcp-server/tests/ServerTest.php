<?php
declare(strict_types=1);

namespace AntonBrutto\McpServerTests;

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpServer\Cache\InMemoryCachePool;
use AntonBrutto\McpServer\Registry;
use AntonBrutto\McpServer\Server;
use AntonBrutto\McpServer\Provider\ToolProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/** @covers \AntonBrutto\McpServer\Server */
final class ServerTest extends TestCase
{
    public function testToolsCallCachesByIdempotencyKey(): void
    {
        $tool = new SpyTool();
        $registry = new Registry();
        $registry->addTool($tool);
        $logger = new SpyLogger();
        $server = new Server($registry, new InMemoryCachePool(), $logger);

        $request = new JsonRpcMessage('1', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['idempotencyKey' => 'abc', 'value' => 42],
        ]);

        $responseA = $server->handle($request);
        $responseB = $server->handle(new JsonRpcMessage('2', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['idempotencyKey' => 'abc', 'value' => 13],
        ]));

        self::assertSame(1, $tool->invocations);
        self::assertSame(['value' => 42], $tool->lastArgs);
        self::assertSame(['result' => ['value' => 42]], $responseA?->result);
        self::assertSame(['result' => ['value' => 42]], $responseB?->result);

        self::assertSame('Invoking tool', $logger->records[0]['message'] ?? null);
        self::assertSame('Returning cached tool result', $logger->records[1]['message'] ?? null);
    }

    public function testToolsCallWithoutKeyInvokesEachTime(): void
    {
        $tool = new SpyTool();
        $registry = new Registry();
        $registry->addTool($tool);
        $server = new Server($registry, new InMemoryCachePool(), new SpyLogger());

        $request = new JsonRpcMessage('1', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['value' => 1],
        ]);

        $server->handle($request);
        $server->handle(new JsonRpcMessage('2', 'tools/call', [
            'name'      => 'spy',
            'arguments' => ['value' => 2],
        ]));

        self::assertSame(2, $tool->invocations);
    }
}

final class SpyTool implements ToolProviderInterface
{
    public int $invocations = 0;

    public array $lastArgs = [];

    public function name(): string
    {
        return 'spy';
    }

    public function description(): ?string
    {
        return 'spy';
    }

    public function parametersSchema(): array
    {
        return [];
    }

    public function invoke(array $args): array
    {
        $this->invocations++;
        $this->lastArgs = $args;

        return ['value' => $args['value'] ?? null];
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string)$level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}
