<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

interface ServerInterface
{
    /** Handle an inbound request and return a response payload or null for notifications. */
    public function onRequest(JsonRpcMessage $msg): ?JsonRpcMessage;

    /** Consume a notification message that does not expect a response. */
    public function onNotification(JsonRpcMessage $msg): void;
}
