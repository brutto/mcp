<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

interface TransportInterface
{
    /** Prepare underlying connection (no-op for already-open transports). */
    public function open(): void;

    /** Send a single JSON-RPC message to the server side. */
    public function send(JsonRpcMessage $msg): void;

    /** Propagate a request cancellation to the server (mcp.cancel notification). */
    public function cancel(string $id, string $method): void;

    /** @return iterable<JsonRpcMessage> */
    public function incoming(): iterable;

    /** Close the connection and release transport resources. */
    public function close(): void;
}
