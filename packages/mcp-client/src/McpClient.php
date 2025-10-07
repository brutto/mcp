<?php
declare(strict_types=1);
namespace AntonBrutto\McpClient;
use AntonBrutto\McpCore\{ProtocolClientInterface, TransportInterface, JsonRpcMessage, Capabilities};
use RuntimeException;
final class McpClient implements ProtocolClientInterface {
    private int $nextId = 1;
    /** @var array<string, JsonRpcMessage> */
    private array $pending = [];
    public function __construct(private TransportInterface $transport) {}
    public function init(Capabilities $want): Capabilities {
        $result = $this->request('mcp.init', ['capabilities' => [
            'tools' => $want->tools,
            'resources' => $want->resources,
            'prompts' => $want->prompts,
            'notifications' => $want->notifications,
        ]]);
        $caps = $result['capabilities'] ?? [];
        return new Capabilities(
            (bool)($caps['tools'] ?? false),
            (bool)($caps['resources'] ?? false),
            (bool)($caps['prompts'] ?? false),
            (bool)($caps['notifications'] ?? false)
        );
    }
    public function listTools(): array {
        $result = $this->request('tools/list');
        return $result['tools'] ?? [];
    }
    public function callTool(string $name, array $args): array {
        $result = $this->request('tools/call', ['name' => $name, 'arguments' => $args]);
        return $result;
    }
    public function listPrompts(): array {
        $result = $this->request('prompts/list');
        return $result['prompts'] ?? [];
    }
    public function getPrompt(string $name, array $args = []): array {
        $result = $this->request('prompts/get', ['name' => $name, 'arguments' => $args]);
        return $result;
    }
    public function listResources(): array {
        $result = $this->request('resources/list');
        return $result['resources'] ?? [];
    }
    public function readResource(string $uri): array {
        $result = $this->request('resources/read', ['uri' => $uri]);
        return $result;
    }
    private function nextId(): string {
        return (string)$this->nextId++;
    }
    private function request(string $method, array $params = []): array {
        $id = $this->nextId();
        $this->transport->send(new JsonRpcMessage($id, $method, $params));
        $response = $this->awaitResponse($id);
        if ($response->error !== null) {
            $message = $response->error['message'] ?? 'Unknown error';
            $code = $response->error['code'] ?? 0;
            throw new RuntimeException("{$method} failed with {$message} ({$code})");
        }
        return $response->result ?? [];
    }
    private function awaitResponse(string $id): JsonRpcMessage {
        if (isset($this->pending[$id])) {
            $msg = $this->pending[$id];
            unset($this->pending[$id]);
            return $msg;
        }
        foreach ($this->transport->incoming() as $message) {
            if ($message->id === null) {
                $this->handleNotification($message);
                continue;
            }
            if ($message->id === $id) {
                return $message;
            }
            $this->pending[$message->id] = $message;
        }
        throw new RuntimeException('Transport closed before receiving response for request ' . $id);
    }
    private function handleNotification(JsonRpcMessage $message): void {}
}
