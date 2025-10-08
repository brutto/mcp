<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer;

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpServer\Cache\InMemoryCachePool;
use AntonBrutto\McpServer\Provider\ToolProviderInterface;
use AntonBrutto\McpServer\Validation\NullSchemaValidator;
use AntonBrutto\McpServer\Validation\SchemaValidationException;
use AntonBrutto\McpServer\Validation\SchemaValidatorInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Primary MCP server implementation handling tool/resource/prompt requests. */
final class Server
{
    public function __construct(
        private readonly Registry $registry,
        private ?CacheItemPoolInterface $cache = null,
        private ?SchemaValidatorInterface $validator = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->cache = $cache ?? new InMemoryCachePool();
        $this->validator = $validator ?? new NullSchemaValidator();
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(JsonRpcMessage $msg): ?JsonRpcMessage
    {
        return match ($msg->method) {
            'mcp.init' => $this->capabilities($msg),
            'tools/list' => $this->toolsList($msg),
            'tools/call' => $this->toolsCall($msg),
            'prompts/list' => $this->promptsList($msg),
            'prompts/get' => $this->promptsGet($msg),
            'resources/list' => $this->resourcesList($msg),
            'resources/read' => $this->resourcesRead($msg),
            default => new JsonRpcMessage($msg->id, $msg->method, [], null,
                ['code' => -32601, 'message' => 'Method not found']),
        };
    }

    /** @param array<int,JsonRpcMessage> $messages */
    public function handleBatch(array $messages): array
    {
        $responses = [];
        foreach ($messages as $message) {
            $response = $this->handle($message);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    private function capabilities(JsonRpcMessage $msg): JsonRpcMessage
    {
        $caps = [
            'tools'         => !empty($this->registry->tools),
            'prompts'       => !empty($this->registry->prompts),
            'resources'     => !empty($this->registry->resources),
            'notifications' => false,
        ];
        return new JsonRpcMessage($msg->id, 'mcp.init', [], ['capabilities' => $caps]);
    }

    private function toolsList(JsonRpcMessage $msg): JsonRpcMessage
    {
        $list = array_map(fn(ToolProviderInterface $t) => [
            'name'        => $t->name(),
            'description' => $t->description(),
            'parameters'  => $t->parametersSchema(),
        ], array_values($this->registry->tools));
        return new JsonRpcMessage($msg->id, 'tools/list', [], ['tools' => $list]);
    }

    private function toolsCall(JsonRpcMessage $msg): JsonRpcMessage
    {
        $name = $msg->params['name'] ?? '';
        $args = $msg->params['arguments'] ?? [];
        $tool = $this->registry->tools[$name] ?? null;
        if (!$tool) {
            return new JsonRpcMessage($msg->id, 'tools/call', [], null,
                ['code' => 404, 'message' => "Tool '$name' not found"]);
        }

        $key = $args['idempotencyKey'] ?? null;
        $cacheItem = $this->resolveCacheItem($name, $key);
        if ($cacheItem !== null && $cacheItem->isHit()) {
            $this->logger->info('Returning cached tool result', [
                'tool'           => $name,
                'idempotencyKey' => $key,
            ]);

            return new JsonRpcMessage($msg->id, 'tools/call', [], $cacheItem->get());
        }

        $this->logger->info('Invoking tool', [
            'tool'           => $name,
            'idempotencyKey' => $key,
        ]);

        $toolArgs = $args;
        unset($toolArgs['idempotencyKey']);

        try {
            $this->validatePayload($tool->parametersSchema(), $toolArgs, sprintf('%s.arguments', $name));
        } catch (SchemaValidationException $e) {
            return $this->validationError($msg->id, 'tools/call', 'tool', $name, $e);
        }

        try {
            $res = $tool->invoke($toolArgs);
        } catch (SchemaValidationException $e) {
            return $this->validationError($msg->id, 'tools/call', 'tool', $name, $e);
        }

        try {
            $this->validatePayload($tool->resultSchema(), $res, sprintf('%s.result', $name));
        } catch (SchemaValidationException $e) {
            return $this->validationError($msg->id, 'tools/call', 'tool', $name, $e);
        }

        if ($cacheItem !== null) {
            $cacheItem->set($res);
            $this->cache->save($cacheItem);
        }

        return new JsonRpcMessage($msg->id, 'tools/call', [], $res);
    }

    private function promptsList(JsonRpcMessage $msg): JsonRpcMessage
    {
        $all = [];
        foreach ($this->registry->prompts as $p) {
            $all = [...$all, ...$p->listPrompts()];
        }
        return new JsonRpcMessage($msg->id, 'prompts/list', [], ['prompts' => $all]);
    }

    private function promptsGet(JsonRpcMessage $msg): JsonRpcMessage
    {
        $name = $msg->params['name'] ?? '';
        $args = $msg->params['arguments'] ?? [];
        foreach ($this->registry->prompts as $provider) {
            $definitions = array_filter($provider->listPrompts(), fn($d) => $d['name'] === $name);
            if (!$definitions) {
                continue;
            }

            $schema = $provider->argumentsSchema($name);
            if ($schema === null && $definitions) {
                $schema = $definitions[array_key_first($definitions)]['arguments'] ?? null;
            }

            try {
                $this->validatePayload($schema, $args, sprintf('%s.arguments', $name));
            } catch (SchemaValidationException $e) {
                return $this->validationError($msg->id, 'prompts/get', 'prompt', $name, $e);
            }

            try {
                return new JsonRpcMessage($msg->id, 'prompts/get', [], $provider->getPrompt($name, $args));
            } catch (SchemaValidationException $e) {
                return $this->validationError($msg->id, 'prompts/get', 'prompt', $name, $e);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }
        return new JsonRpcMessage($msg->id, 'prompts/get', [], null,
            ['code' => 404, 'message' => "Prompt '$name' not found"]);
    }

    private function resourcesList(JsonRpcMessage $msg): JsonRpcMessage
    {
        $all = [];
        foreach ($this->registry->resources as $r) {
            $all = [...$all, ...$r->listResources()];
        }
        return new JsonRpcMessage($msg->id, 'resources/list', [], ['resources' => $all]);
    }

    private function resourcesRead(JsonRpcMessage $msg): JsonRpcMessage
    {
        $uri = $msg->params['uri'] ?? '';
        foreach ($this->registry->resources as $r) {
            try {
                $res = $r->readResource($uri);
                return new JsonRpcMessage($msg->id, 'resources/read', [], $res);
            } catch (\InvalidArgumentException $e) { /* не этот провайдер */
            }
        }
        return new JsonRpcMessage($msg->id, 'resources/read', [], null,
            ['code' => 404, 'message' => "Resource '$uri' not found"]);
    }

    private function resolveCacheItem(string $tool, ?string $key): ?CacheItemInterface
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->cache->getItem($this->cacheKey($tool, $key));
    }

    private function cacheKey(string $tool, string $key): string
    {
        return sprintf('tool:%s:%s', $tool, $key);
    }

    private function validatePayload(?array $schema, mixed $payload, string $context): void
    {
        if ($schema === null || $schema === []) {
            return;
        }

        try {
            $this->validator->validate($schema, $payload, $context);
        } catch (SchemaValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SchemaValidationException($context, $e->getMessage());
        }
    }

    private function validationError(
        ?string $id,
        string $method,
        string $subjectType,
        string $subjectName,
        SchemaValidationException $e
    ): JsonRpcMessage {
        $this->logger->warning('Payload failed validation', [
            'subjectType' => $subjectType,
            'subjectName' => $subjectName,
            'context'     => $e->context,
            'error'       => $e->getMessage(),
        ]);

        return new JsonRpcMessage($id, $method, [], null, [
            'code'    => 422,
            'message' => sprintf('Payload validation failed: %s', $e->getMessage()),
            'data'    => ['context' => $e->context, 'subjectType' => $subjectType, 'subjectName' => $subjectName],
        ]);
    }
}
