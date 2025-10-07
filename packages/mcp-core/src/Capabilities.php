<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

/** Immutable view of protocol capabilities negotiated between client and server. */
final class Capabilities
{
    public function __construct(
        /** Whether tool discovery and invocation are supported. */
        public bool $tools = true,
        /** Whether resource listing/reading is supported. */
        public bool $resources = true,
        /** Whether prompt discovery and retrieval are supported. */
        public bool $prompts = true,
        /** Whether server-to-client notifications are supported. */
        public bool $notifications = true,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }
}
