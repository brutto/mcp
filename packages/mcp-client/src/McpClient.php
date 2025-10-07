<?php
declare(strict_types=1);
namespace AntonBrutto\McpClient;
use AntonBrutto\McpCore\{ProtocolClientInterface, TransportInterface, JsonRpcMessage, Capabilities};
final class McpClient implements ProtocolClientInterface {
    public function __construct(private TransportInterface $transport) {}
    public function init(Capabilities $want): Capabilities {
        $this->transport->send(new JsonRpcMessage('1', 'mcp.init', ['capabilities' => [
            'tools' => $want->tools, 'resources' => $want->resources, 'prompts' => $want->prompts, 'notifications' => $want->notifications
        ]]));
        return $want;
    }
    public function listTools(): array {
        $this->transport->send(new JsonRpcMessage('2', 'tools/list'));
        return [];
    }
    public function callTool(string $name, array $args): array {
        $this->transport->send(new JsonRpcMessage('3', 'tools/call', ['name' => $name, 'arguments' => $args]));
        return ['status' => 'sent'];
    }
    public function listPrompts(): array { $this->transport->send(new JsonRpcMessage('4', 'prompts/list')); return []; }
    public function getPrompt(string $name, array $args = []): array {
        $this->transport->send(new JsonRpcMessage('5', 'prompts/get', ['name' => $name, 'arguments' => $args]));
        return [];
    }
    public function listResources(): array { $this->transport->send(new JsonRpcMessage('6', 'resources/list')); return []; }
    public function readResource(string $uri): array {
        $this->transport->send(new JsonRpcMessage('7', 'resources/read', ['uri' => $uri]));
        return [];
    }
}
