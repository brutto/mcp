<?php
declare(strict_types=1);

namespace AntonBrutto\McpCodecJson;

use AntonBrutto\McpCore\JsonRpcMessage;

final class JsonCodec
{
    public static function encode(JsonRpcMessage $m): string
    {
        $payload = ['jsonrpc' => JsonRpcMessage::VERSION, 'method' => $m->method];
        if ($m->id !== null) {
            $payload['id'] = $m->id;
        }
        if ($m->params) {
            $payload['params'] = $m->params;
        }
        if ($m->result !== null) {
            $payload['result'] = $m->result;
        }
        if ($m->error !== null) {
            $payload['error'] = $m->error;
        }
        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $json): JsonRpcMessage
    {
        $d = json_decode($json, true);
        return new JsonRpcMessage(
            $d['id'] ?? null,
            $d['method'] ?? '',
            $d['params'] ?? [],
            $d['result'] ?? null,
            $d['error'] ?? null
        );
    }
}
