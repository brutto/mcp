<?php
declare(strict_types=1);
namespace AntonBrutto\McpCore;
final class Capabilities {
    public function __construct(
        public bool $tools = true,
        public bool $resources = true,
        public bool $prompts = true,
        public bool $notifications = true,
    ) {}
    public static function all(): self { return new self(); }
}
