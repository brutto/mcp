<?php
declare(strict_types=1);

namespace AntonBrutto\McpClientTests;

use AntonBrutto\McpClient\McpClient;
use AntonBrutto\McpCore\CancellationException;
use AntonBrutto\McpCore\CancellationToken;
use AntonBrutto\McpCore\Capabilities;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TimeoutException;
use AntonBrutto\McpCore\TransportInterface;
use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class McpClientTest extends TestCase
{
    public function testInitReturnsCapabilitiesNegotiatedByServer(): void
    {
        $transport = new FakeTransport([
            'mcp.init' => static function (JsonRpcMessage $msg): array {
                return [
                    new JsonRpcMessage($msg->id, $msg->method, [], [
                        'capabilities' => [
                            'tools' => true,
                            'resources' => false,
                            'prompts' => true,
                            'notifications' => false,
                        ],
                    ]),
                ];
            },
        ]);

        $client = new McpClient($transport);
        $caps = $client->init(Capabilities::all());

        self::assertTrue($caps->tools);
        self::assertFalse($caps->resources);
        self::assertTrue($caps->prompts);
        self::assertFalse($caps->notifications);
    }

    public function testListToolsParsesResultAndIgnoresNotifications(): void
    {
        $transport = new FakeTransport([
            'tools/list' => static function (JsonRpcMessage $msg): array {
                return [
                    new JsonRpcMessage(null, 'notify/something', ['payload' => 'ignored']),
                    new JsonRpcMessage($msg->id, $msg->method, [], [
                        'tools' => [
                            ['name' => 'echo'],
                            ['name' => 'reverse'],
                        ],
                    ]),
                ];
            },
        ]);

        $transport->push(new JsonRpcMessage(null, 'notify/pre-seeded'));

        $client = new McpClient($transport);
        $tools = $client->listTools();

        self::assertSame([
            ['name' => 'echo'],
            ['name' => 'reverse'],
        ], $tools);
    }

    public function testCallToolThrowsRuntimeExceptionOnError(): void
    {
        $transport = new FakeTransport([
            'tools/call' => static function (JsonRpcMessage $msg): array {
                return [
                    new JsonRpcMessage($msg->id, $msg->method, [], null, [
                        'code' => 123,
                        'message' => 'Boom',
                    ]),
                ];
            },
        ]);

        $client = new McpClient($transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tools/call failed with Boom (123)');

        try {
            $client->callTool('some-tool', []);
        } finally {
            $payload = $transport->sent[0]->params['arguments'] ?? [];
            self::assertArrayHasKey('idempotencyKey', $payload);
        }
    }

    public function testCallToolEnrichesArgumentsWithIdempotencyKeyAndLogs(): void
    {
        $transport = new FakeTransport([
            'tools/call' => static function (JsonRpcMessage $msg): array {
                return [
                    new JsonRpcMessage($msg->id, $msg->method, [], ['result' => 'ok']),
                ];
            },
        ]);

        $logger = new SpyLogger();
        $client = new McpClient($transport, $logger);

        $result = $client->callTool('side-effect', ['foo' => 'bar']);

        $payload = $transport->sent[0]->params['arguments'] ?? [];
        self::assertArrayHasKey('idempotencyKey', $payload);
        self::assertSame('bar', $payload['foo']);
        self::assertSame(['result' => 'ok'], $result);
        self::assertCount(2, $logger->records);
        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('Calling tool', $logger->records[0]['message']);
        self::assertSame('side-effect', $logger->records[0]['context']['tool'] ?? null);
        self::assertSame('Tool call completed', $logger->records[1]['message']);
    }

    public function testCallToolRespectsProvidedIdempotencyKey(): void
    {
        $transport = new FakeTransport([
            'tools/call' => static function (JsonRpcMessage $msg): array {
                return [new JsonRpcMessage($msg->id, $msg->method, [], ['ok' => true])];
            },
        ]);

        $client = new McpClient($transport, new SpyLogger());
        $client->callTool('side-effect', ['idempotencyKey' => 'fixed', 'x' => 1]);

        $payload = $transport->sent[0]->params['arguments'] ?? [];
        self::assertSame('fixed', $payload['idempotencyKey'] ?? null);
        self::assertSame(1, $payload['x'] ?? null);
    }

    public function testRequestTimeoutThrowsTimeoutException(): void
    {
        $transport = new FakeTransport();
        $client = new McpClient($transport);

        $this->expectException(TimeoutException::class);

        try {
            $client->listTools(timeoutSeconds: 0.0);
        } finally {
            self::assertSame('mcp.cancel', $transport->sent[1]->method ?? null);
        }
    }

    public function testCancelledTokenAbortsRequest(): void
    {
        $transport = new FakeTransport();
        $client = new McpClient($transport);
        $token = CancellationToken::none();
        $token->cancel();

        $this->expectException(CancellationException::class);

        try {
            $client->listTools($token);
        } finally {
            self::assertCount(0, $transport->sent);
        }
    }

    public function testCancellationTokenAfterSendEmitsCancelNotification(): void
    {
        $transport = new FakeTransport();
        $client = new McpClient($transport);
        $token = CancellationToken::fromTimeout(0.01);

        $this->expectException(CancellationException::class);

        try {
            $client->listTools($token);
        } finally {
            self::assertSame('mcp.cancel', $transport->sent[1]->method ?? null);
            self::assertSame([
                'id' => '1',
                'method' => 'tools/list',
            ], $transport->sent[1]->params ?? []);
        }
    }

    /**
     * Ensures batching multiple RPCs returns structured responses keyed by input.
     *
     * @return void
     */
    public function testRequestBatchReturnsResponses(): void
    {
        $transport = new FakeTransport([
            'tools/list' => static function (JsonRpcMessage $msg): array {
                return [new JsonRpcMessage($msg->id, $msg->method, [], ['tools' => [['name' => 'echo']]])];
            },
            'prompts/list' => static function (JsonRpcMessage $msg): array {
                return [new JsonRpcMessage($msg->id, $msg->method, [], ['prompts' => [['name' => 'releaseNotes']]])];
            },
        ]);

        $client = new McpClient($transport);

        $responses = $client->requestBatch([
            'tools' => ['method' => 'tools/list'],
            'prompts' => ['method' => 'prompts/list'],
        ]);

        self::assertArrayHasKey('tools', $responses);
        self::assertArrayHasKey('prompts', $responses);
        self::assertInstanceOf(JsonRpcMessage::class, $responses['tools']);
        self::assertInstanceOf(JsonRpcMessage::class, $responses['prompts']);
        self::assertSame(['tools' => [['name' => 'echo']]], $responses['tools']->result);
        self::assertSame(['prompts' => [['name' => 'releaseNotes']]], $responses['prompts']->result);
    }

    /**
     * Verifies batch input with invalid shape raises an exception before dispatch.
     *
     * @return void
     */
    public function testRequestBatchValidatesStructure(): void
    {
        $client = new McpClient(new FakeTransport());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch entry requires a non-empty "method" field.');

        $client->requestBatch([
            ['params' => []],
        ]);
    }

    /**
     * Confirms batch keeps error payloads accessible to the caller.
     *
     * @return void
     */
    public function testRequestBatchReturnsErrorMessages(): void
    {
        $transport = new FakeTransport([
            'tools/call' => static function (JsonRpcMessage $msg): array {
                return [
                    new JsonRpcMessage($msg->id, $msg->method, [], null, [
                        'code' => 500,
                        'message' => 'Internal failure',
                    ]),
                ];
            },
        ]);

        $client = new McpClient($transport);

        $responses = $client->requestBatch([
            'call' => ['method' => 'tools/call', 'params' => ['name' => 'broken', 'arguments' => []]],
        ]);

        self::assertSame('tools/call', $responses['call']->method);
        self::assertSame(500, $responses['call']->error['code'] ?? null);
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class FakeTransport implements TransportInterface
{
    /** @var array<string, callable(JsonRpcMessage): iterable<JsonRpcMessage>> */
    private array $handlers;

    /** @var list<JsonRpcMessage|array<int,JsonRpcMessage>> */
    private array $queue = [];

    /** @var list<JsonRpcMessage> */
    public array $sent = [];

    /** @param array<string, callable(JsonRpcMessage): iterable<JsonRpcMessage>> $handlers */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers;
    }

    public function open(): void {}

    public function send(JsonRpcMessage $msg): void
    {
        $this->sent[] = $msg;

        foreach ($this->processMessage($msg) as $response) {
            $this->queue[] = $response;
        }
    }

    public function sendBatch(array $messages): void
    {
        $batch = [];

        foreach ($messages as $msg) {
            if (!$msg instanceof JsonRpcMessage) {
                throw new RuntimeException('FakeTransport::sendBatch expects JsonRpcMessage instances');
            }
            $this->sent[] = $msg;

            foreach ($this->processMessage($msg) as $response) {
                $batch[] = $response;
            }
        }

        if ($batch !== []) {
            $this->queue[] = $batch;
        }
    }

    public function cancel(string $id, string $method): void
    {
        $cancel = new JsonRpcMessage(null, 'mcp.cancel', ['id' => $id, 'method' => $method]);
        $this->sent[] = $cancel;

        if (!isset($this->handlers['mcp.cancel'])) {
            return;
        }

        $responses = ($this->handlers['mcp.cancel'])($cancel);
        if (!is_iterable($responses)) {
            $responses = [$responses];
        }

        foreach ($responses as $response) {
            if (!$response instanceof JsonRpcMessage) {
                throw new RuntimeException('FakeTransport expects JsonRpcMessage instances');
            }
            $this->queue[] = $response;
        }
    }

    public function incoming(): iterable
    {
        while ($this->queue) {
            yield array_shift($this->queue);
        }
    }

    public function close(): void {}

    public function push(JsonRpcMessage $msg): void
    {
        $this->queue[] = $msg;
    }

    /** @return list<JsonRpcMessage> */
    private function processMessage(JsonRpcMessage $msg): array
    {
        if (!isset($this->handlers[$msg->method])) {
            return [];
        }

        $responses = ($this->handlers[$msg->method])($msg);
        if (!is_iterable($responses)) {
            $responses = [$responses];
        }

        $result = [];
        foreach ($responses as $response) {
            if (!$response instanceof JsonRpcMessage) {
                throw new RuntimeException('FakeTransport expects JsonRpcMessage instances');
            }
            $result[] = $response;
        }

        return $result;
    }
}
