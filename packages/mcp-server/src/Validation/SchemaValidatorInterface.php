<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Validation;

use AntonBrutto\McpServer\Validation\SchemaValidationException;

interface SchemaValidatorInterface
{
    /**
     * @param array<string,mixed> $schema JSON Schema (draft-07).
     * @param mixed $payload Data to validate.
     * @throws SchemaValidationException when payload does not satisfy schema.
     */
    public function validate(array $schema, mixed $payload, string $context): void;
}
