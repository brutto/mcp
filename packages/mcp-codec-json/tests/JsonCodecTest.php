<?php
declare(strict_types=1);

/**
 * JsonCodec behaviour test suite.
 */
namespace AntonBrutto\McpCodecJsonTests;

use AntonBrutto\McpCodecJson\JsonCodec;
use AntonBrutto\McpCore\JsonRpcMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AntonBrutto\McpCodecJson\JsonCodec
 */
final class JsonCodecTest extends TestCase
{
    /**
     * Ensures a single message is encoded with optional sections when provided.
     *
     * @return void
     */
    public function testEncodeSerializesSingleMessage(): void
    {
        $message = new JsonRpcMessage('42', 'tools/echo', ['foo' => 'bar'], ['ok' => true]);

        $encoded = JsonCodec::encode($message);

        self::assertSame(
            '{"jsonrpc":"2.0","method":"tools/echo","id":"42","params":{"foo":"bar"},"result":{"ok":true}}',
            $encoded
        );
    }

    /**
     * Ensures a batch of messages is encoded without losing ordering.
     *
     * @return void
     */
    public function testEncodeBatchSerializesMultipleMessages(): void
    {
        $messages = [
            new JsonRpcMessage('1', 'tools/first', ['x' => 1]),
            new JsonRpcMessage(null, 'notify/something', ['payload' => 'value']),
        ];

        $encoded = JsonCodec::encodeBatch($messages);

        self::assertSame(
            '[{"jsonrpc":"2.0","method":"tools/first","id":"1","params":{"x":1}},' .
            '{"jsonrpc":"2.0","method":"notify/something","params":{"payload":"value"}}]',
            $encoded
        );
    }

    /**
     * Ensures decode returns a JsonRpcMessage instance for single payloads.
     *
     * @return void
     */
    public function testDecodeReturnsSingleMessage(): void
    {
        $message = JsonCodec::decode(
            '{"jsonrpc":"2.0","method":"tools/result","id":"abc","result":{"value":"done"}}'
        );

        self::assertInstanceOf(JsonRpcMessage::class, $message);
        self::assertSame('abc', $message->id);
        self::assertSame('tools/result', $message->method);
        self::assertSame([], $message->params);
        self::assertSame(['value' => 'done'], $message->result);
        self::assertNull($message->error);
    }

    /**
     * Ensures decode returns a list of JsonRpcMessage instances for batch payloads.
     *
     * @return void
     */
    public function testDecodeReturnsBatchOfMessages(): void
    {
        $messages = JsonCodec::decode(
            '[{"jsonrpc":"2.0","method":"notify/skip","params":{"x":1}},' .
            '{"jsonrpc":"2.0","method":"tools/list","id":"2","result":{"tools":[]}}]'
        );

        self::assertIsArray($messages);
        self::assertCount(2, $messages);
        self::assertContainsOnlyInstancesOf(JsonRpcMessage::class, $messages);
        self::assertSame('notify/skip', $messages[0]->method);
        self::assertNull($messages[0]->id);
        self::assertSame(['tools' => []], $messages[1]->result);
    }

    /**
     * Ensures decode rejects payloads that do not represent JSON-RPC structures.
     *
     * @return void
     */
    public function testDecodeRejectsInvalidPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC payload');

        JsonCodec::decode('"plain-string"');
    }

    /**
     * Ensures decode rejects batch entries that are not JSON objects.
     *
     * @return void
     */
    public function testDecodeRejectsBatchEntryThatIsNotObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch entry at index 0 is not an object');

        JsonCodec::decode('[42]');
    }
}
