<?php
declare(strict_types=1);

namespace AntonBrutto\McpClient;

use AntonBrutto\McpClient\Internal\Deferred;
use AntonBrutto\McpCore\{Capabilities,
    CancellationException,
    CancellationToken,
    JsonRpcMessage,
    ProtocolClientInterface,
    TransportInterface
};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

final class McpClient implements ProtocolClientInterface
{
    private int $nextId = 1;

    /** @var array<string, Deferred> */
    private array $requests = [];

    /** @var array<string, JsonRpcMessage> */
    private array $pending = [];

    public function __construct(private TransportInterface $transport, private ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function init(
        Capabilities $want,
        ?CancellationToken $cancel = null,
        ?float $timeoutSeconds = null
    ): Capabilities {
        $result = $this->request('mcp.init', [
            'capabilities' => [
                'tools'         => $want->tools,
                'resources'     => $want->resources,
                'prompts'       => $want->prompts,
                'notifications' => $want->notifications,
            ],
        ], $cancel, $timeoutSeconds);

        $caps = $result['capabilities'] ?? [];

        return new Capabilities(
            (bool)($caps['tools'] ?? false),
            (bool)($caps['resources'] ?? false),
            (bool)($caps['prompts'] ?? false),
            (bool)($caps['notifications'] ?? false)
        );
    }

    public function listTools(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array
    {
        $result = $this->request('tools/list', [], $cancel, $timeoutSeconds);

        return $result['tools'] ?? [];
    }

    public function callTool(
        string $name,
        array $args,
        ?CancellationToken $cancel = null,
        ?float $timeoutSeconds = null
    ): array {
        $idempotencyKey = $args['idempotencyKey'] ?? $this->nextIdempotencyKey();
        $enrichedArgs = ['idempotencyKey' => $idempotencyKey] + $args;

        $this->logger->info('Calling tool', [
            'tool'           => $name,
            'idempotencyKey' => $idempotencyKey,
            'arguments'      => $args,
        ]);

        $result = $this->request(
            'tools/call',
            ['name' => $name, 'arguments' => $enrichedArgs],
            $cancel,
            $timeoutSeconds
        );

        $this->logger->info('Tool call completed', [
            'tool'           => $name,
            'idempotencyKey' => $idempotencyKey,
            'result'         => $result,
        ]);

        return $result;
    }

    public function listPrompts(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array
    {
        $result = $this->request('prompts/list', [], $cancel, $timeoutSeconds);

        return $result['prompts'] ?? [];
    }

    public function getPrompt(
        string $name,
        array $args = [],
        ?CancellationToken $cancel = null,
        ?float $timeoutSeconds = null
    ): array {
        return $this->request('prompts/get', ['name' => $name, 'arguments' => $args], $cancel, $timeoutSeconds);
    }

    public function listResources(?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array
    {
        $result = $this->request('resources/list', [], $cancel, $timeoutSeconds);

        return $result['resources'] ?? [];
    }

    public function readResource(string $uri, ?CancellationToken $cancel = null, ?float $timeoutSeconds = null): array
    {
        return $this->request('resources/read', ['uri' => $uri], $cancel, $timeoutSeconds);
    }

    private function nextId(): string
    {
        return (string)$this->nextId++;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        array $params = [],
        ?CancellationToken $cancel = null,
        ?float $timeoutSeconds = null
    ): array {
        $id = $this->nextId();

        if ($cancel?->isCancelled()) {
            throw new CancellationException(sprintf('Request %s was cancelled before send.', $method));
        }

        $deadline = $timeoutSeconds !== null ? microtime(true) + $timeoutSeconds : null;

        $deferred = new Deferred(
            $id,
            $method,
            $cancel,
            $deadline,
            fn() => $this->cancelRequest($id, $method)
        );
        $this->requests[$id] = $deferred;

        if (isset($this->pending[$id])) {
            $deferred->resolve($this->pending[$id]);
            unset($this->pending[$id]);
        }

        $this->transport->send(new JsonRpcMessage($id, $method, $params));

        try {
            $response = $this->await($deferred);
        } finally {
            unset($this->requests[$id]);
        }

        if ($response->error !== null) {
            $message = $response->error['message'] ?? 'Unknown error';
            $code = $response->error['code'] ?? 0;

            throw new RuntimeException("{$method} failed with {$message} ({$code})");
        }

        return $response->result ?? [];
    }

    private function await(Deferred $deferred): JsonRpcMessage
    {
        while (true) {
            $deferred->guard();

            if ($deferred->isSettled()) {
                return $deferred->message();
            }

            $processed = $this->pumpIncoming($deferred);

            if (!$processed && !$deferred->isSettled()) {
                // Avoid busy loop while still polling for incoming messages.
                usleep(5_000);
            }
        }
    }

    private function pumpIncoming(Deferred $target): bool
    {
        $processed = false;

        foreach ($this->transport->incoming() as $payload) {
            $processed = true;
            if (is_array($payload)) {
                foreach ($payload as $message) {
                    $this->dispatchMessage($message);
                    if ($target->isSettled()) {
                        break;
                    }
                }
            } else {
                $this->dispatchMessage($payload);
            }

            if ($target->isSettled()) {
                break;
            }
        }

        if (!$processed && !$target->isSettled()) {
            // No message received; treat as transport closed if there are no active requests.
            if (empty($this->requests)) {
                $target->reject(new RuntimeException('Transport closed before receiving any response.'));
            }
        }

        return $processed;
    }

    private function dispatchMessage(JsonRpcMessage $message): void
    {
        if ($message->id === null) {
            $this->handleNotification($message);

            return;
        }

        if (isset($this->requests[$message->id])) {
            $this->requests[$message->id]->resolve($message);

            return;
        }

        $this->pending[$message->id] = $message;
    }

    private function handleNotification(JsonRpcMessage $message): void
    {
    }

    private function cancelRequest(string $id, string $method): void
    {
        $this->transport->cancel($id, $method);
    }

    private function nextIdempotencyKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
