<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Validation;

use RuntimeException;

final class SchemaValidationException extends RuntimeException
{
    public function __construct(public readonly string $context, string $message)
    {
        parent::__construct($message);
    }
}
