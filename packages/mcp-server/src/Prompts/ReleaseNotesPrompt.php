<?php

// packages/mcp-server/src/Prompts/ReleaseNotesPrompt.php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Prompts;

use AntonBrutto\McpServer\Provider\PromptProviderInterface;

final class ReleaseNotesPrompt implements PromptProviderInterface
{
    public function listPrompts(): array
    {
        return [
            [
                'name'        => 'release_notes',
                'description' => 'Template for composing release notes',
                'arguments'   => [
                    'type'       => 'object',
                    'properties' => [
                        'version' => ['type' => 'string'],
                        'changes' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required'   => ['version', 'changes'],
                ],
            ],
        ];
    }

    public function getPrompt(string $name, array $args = []): array
    {
        if ($name !== 'release_notes') {
            throw new \InvalidArgumentException();
        }
        $ver = $args['version'] ?? '0.0.0';
        $list = array_map(fn($c) => "- $c", $args['changes'] ?? []);
        return [
            'content' => "Release $ver\n\n" . implode("\n", $list),
            'meta'    => ['format' => 'text/plain'],
        ];
    }
}
