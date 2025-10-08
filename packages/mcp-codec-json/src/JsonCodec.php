<?php
declare(strict_types=1);

namespace AntonBrutto\McpCodecJson;

use AntonBrutto\McpCore\JsonRpcMessage;

final class JsonCodec
{
    public static function encode(JsonRpcMessage $m): string
    {
        $payload = self::messageToArray($m);
        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /** @param array<int,JsonRpcMessage> $messages */
    public static function encodeBatch(array $messages): string
    {
        $payload = [];
        foreach ($messages as $message) {
            $payload[] = self::messageToArray($message);
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /** @return JsonRpcMessage|array<int,JsonRpcMessage> */
    public static function decode(string $json): JsonRpcMessage|array
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            $messages = [];
            foreach ($decoded as $offset => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Batch entry at index ' . $offset . ' is not an object');
                }
                $messages[] = self::fromArray($item);
            }

            return $messages;
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid JSON-RPC payload');
        }

        return self::fromArray($decoded);
    }

    private static function messageToArray(JsonRpcMessage $m): array
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
        return $payload;
    }

    private static function fromArray(array $data): JsonRpcMessage
    {
        return new JsonRpcMessage(
            $data['id'] ?? null,
            $data['method'] ?? '',
            $data['params'] ?? [],
            $data['result'] ?? null,
            $data['error'] ?? null
        );
    }
}
