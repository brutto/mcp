<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer\Tools;

use AntonBrutto\McpServer\Provider\ToolProviderInterface;

final class EchoTool implements ToolProviderInterface
{
    public function name(): string
    {
        return 'echo';
    }

    public function description(): ?string
    {
        return 'Echoes back provided input';
    }

    public function parametersSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => ['text' => ['type' => 'string']],
            'required'             => ['text'],
            'additionalProperties' => false,
        ];
    }

    public function invoke(array $args): array
    {
        return ['echo' => (string)($args['text'] ?? '')];
    }
}

