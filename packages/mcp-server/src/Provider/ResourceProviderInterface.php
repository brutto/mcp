<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer\Provider;

interface ResourceProviderInterface
{
    /** Для resources/list */
    public function listResources(): array; // каждый: ['uri'=>'scheme://id', 'name'=>..., 'mime'=>...]

    /** Для resources/read */
    public function readResource(string $uri): array; // ['mime'=>'text/plain', 'data'=>'...'] или ['bytes'=>base64]
}
