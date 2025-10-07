<?php
declare(strict_types=1);
namespace AntonBrutto\McpCore;
final class JsonRpcMessage {
    public const VERSION = '2.0';
    public function __construct(
        public readonly ?string $id,
        public readonly string $method,
        public readonly array $params = [],
        public readonly ?array $result = null,
        public readonly ?array $error = null
    ) {}
}
