<?php
declare(strict_types=1);

namespace AntonBrutto\McpServerTests;

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpServer\SimpleServer;
use PHPUnit\Framework\TestCase;

final class SimpleServerTest extends TestCase
{
    private SimpleServer $server;

    protected function setUp(): void
    {
        $this->server = new SimpleServer();
    }

    public function testInitMirrorsCapabilities(): void
    {
        $request = new JsonRpcMessage('1', 'mcp.init', [
            'capabilities' => [
                'tools' => true,
                'resources' => false,
                'prompts' => false,
                'notifications' => true,
            ],
        ]);

        $response = $this->server->onRequest($request);

        self::assertNotNull($response);
        self::assertSame('1', $response->id);
        self::assertSame('mcp.init', $response->method);
        self::assertSame([
            'capabilities' => [
                'tools' => true,
                'resources' => false,
                'prompts' => false,
                'notifications' => true,
            ],
        ], $response->result);
    }

    public function testToolsListIncludesEchoTool(): void
    {
        $request = new JsonRpcMessage('2', 'tools/list');
        $response = $this->server->onRequest($request);

        self::assertNotNull($response);
        self::assertSame('2', $response->id);
        self::assertSame('tools/list', $response->method);
        self::assertSame([
            'tools' => [
                ['name' => 'echo', 'description' => 'echoes input'],
            ],
        ], $response->result);
    }

    public function testUnknownMethodProducesError(): void
    {
        $request = new JsonRpcMessage('3', 'unknown/method');
        $response = $this->server->onRequest($request);

        self::assertNotNull($response);
        self::assertSame('3', $response->id);
        self::assertSame('unknown/method', $response->method);
        self::assertNull($response->result);
        self::assertSame([
            'code' => -32601,
            'message' => 'Method not found',
        ], $response->error);
    }
}
