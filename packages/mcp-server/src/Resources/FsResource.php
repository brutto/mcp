<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer\Resources;

use AntonBrutto\McpServer\Provider\ResourceProviderInterface;

final class FsResource implements ResourceProviderInterface
{
    public function __construct(private readonly string $rootDir)
    {
    }

    public function listResources(): array
    {
        // Листинг примерных ресурсов (реально можно кешировать/фильтровать)
        return [['uri' => 'fs://README.md', 'name' => 'README', 'mime' => 'text/plain']];
    }

    public function readResource(string $uri): array
    {
        if (!str_starts_with($uri, 'fs://')) {
            throw new \InvalidArgumentException();
        }
        $rel = substr($uri, 5);
        $path = realpath($this->rootDir . '/' . $rel);
        if (!$path || !str_starts_with($path, realpath($this->rootDir))) {
            throw new \InvalidArgumentException('Forbidden path');
        }
        $data = file_get_contents($path);
        return ['mime' => 'text/plain', 'data' => $data];
    }
}
