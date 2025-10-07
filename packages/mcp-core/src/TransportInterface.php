<?php
declare(strict_types=1);
namespace AntonBrutto\McpCore;
interface TransportInterface {
    public function open(): void;
    public function send(JsonRpcMessage $msg): void;
    /** @return iterable<JsonRpcMessage> */
    public function incoming(): iterable;
    public function close(): void;
}
