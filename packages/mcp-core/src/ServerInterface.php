<?php
declare(strict_types=1);
namespace AntonBrutto\McpCore;
interface ServerInterface {
    public function onRequest(JsonRpcMessage $msg): ?JsonRpcMessage;
    public function onNotification(JsonRpcMessage $msg): void;
}
