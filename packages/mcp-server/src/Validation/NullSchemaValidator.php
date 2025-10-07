<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Validation;

/** Default no-op schema validator; accepts everything. */
final class NullSchemaValidator implements SchemaValidatorInterface
{
    public function validate(array $schema, mixed $payload, string $context): void
    {
        // intentionally no-op
    }
}
