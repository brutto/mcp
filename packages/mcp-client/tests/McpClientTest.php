<?php
declare(strict_types=1);

namespace AntonBrutto\McpClientTests;

use AntonBrutto\McpClient\McpClient;
use AntonBrutto\McpCore\Capabilities;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TransportInterface;
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

        $client->callTool('some-tool', []);
    }
}

final class FakeTransport implements TransportInterface
{
    /** @var array<string, callable(JsonRpcMessage): iterable<JsonRpcMessage>> */
    private array $handlers;

    /** @var list<JsonRpcMessage> */
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

        if (!isset($this->handlers[$msg->method])) {
            return;
        }

        $responses = ($this->handlers[$msg->method])($msg);
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
}
