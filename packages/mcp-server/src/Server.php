<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer;

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpServer\Cache\InMemoryCachePool;
use AntonBrutto\McpServer\Provider\ToolProviderInterface;
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
        private ?LoggerInterface $logger = null,
    ) {
        $this->cache = $cache ?? new InMemoryCachePool();
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
                'tool' => $name,
                'idempotencyKey' => $key,
            ]);

            return new JsonRpcMessage($msg->id, 'tools/call', [], ['result' => $cacheItem->get()]);
        }

        $this->logger->info('Invoking tool', [
            'tool' => $name,
            'idempotencyKey' => $key,
        ]);

        $toolArgs = $args;
        unset($toolArgs['idempotencyKey']);

        $res = $tool->invoke($toolArgs);

        if ($cacheItem !== null) {
            $cacheItem->set($res);
            $this->cache->save($cacheItem);
        }

        return new JsonRpcMessage($msg->id, 'tools/call', [], ['result' => $res]);
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
        foreach ($this->registry->prompts as $p) {
            $def = array_filter($p->listPrompts(), fn($d) => $d['name'] === $name);
            if ($def) {
                return new JsonRpcMessage($msg->id, 'prompts/get', [], $p->getPrompt($name, $args));
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
}
