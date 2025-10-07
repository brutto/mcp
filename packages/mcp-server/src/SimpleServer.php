<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer;

use AntonBrutto\McpCore\{ServerInterface, JsonRpcMessage};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SimpleServer implements ServerInterface
{
    public function __construct(private ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onRequest(JsonRpcMessage $msg): ?JsonRpcMessage
    {
        switch ($msg->method) {
            case 'mcp.init':
                return new JsonRpcMessage($msg->id, $msg->method, [],
                    ['capabilities' => $msg->params['capabilities'] ?? []]);
            case 'tools/list':
                return new JsonRpcMessage($msg->id, $msg->method, [],
                    ['tools' => [['name' => 'echo', 'description' => 'echoes input']]]);
            case 'tools/call':
                $args = $msg->params['arguments'] ?? [];
                $this->logger->info('Tool invoked', [
                    'tool'           => $msg->params['name'] ?? 'unknown',
                    'idempotencyKey' => $args['idempotencyKey'] ?? null,
                ]);
                return new JsonRpcMessage($msg->id, $msg->method, [], ['result' => $args]);
            default:
                return new JsonRpcMessage($msg->id, $msg->method, [], null,
                    ['code' => -32601, 'message' => 'Method not found']);
        }
    }

    public function onNotification(JsonRpcMessage $msg): void
    { /* no-op */
    }
}
