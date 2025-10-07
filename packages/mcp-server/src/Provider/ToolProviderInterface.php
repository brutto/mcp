<?php

declare(strict_types=1);

namespace AntonBrutto\McpServer\Provider;

interface ToolProviderInterface
{
    public function name(): string;

    public function description(): ?string;

    /** JSON Schema (draft-07) для аргументов инструмента */
    public function parametersSchema(): array;

    /** Выполнить инструмент; вернуть произвольный массив (будет сериализован в JSON) */
    public function invoke(array $args): array;
}

