<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer\Provider;

interface PromptProviderInterface
{
    /** Для prompts/list */
    public function listPrompts(): array; // каждый: ['name'=>..., 'description'=>..., 'arguments'=>schema-array]

    /** Для prompts/get */
    public function getPrompt(string $name, array $args = []): array; // ['content' => '...', 'meta'=>...]

    /** Возвращает JSON Schema (draft-07) для аргументов указанного промпта. */
    public function argumentsSchema(string $name): ?array;
}
