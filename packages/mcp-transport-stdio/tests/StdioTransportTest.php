<?php
declare(strict_types=1);

/**
 * PHPUnit acceptance tests for the STDIO MCP transport adapter.
 */
namespace AntonBrutto\McpTransportStdioTests;

use AntonBrutto\McpCodecJson\JsonCodec;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpTransportStdio\StdioTransport;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AntonBrutto\McpTransportStdio\StdioTransport
 */
final class StdioTransportTest extends TestCase
{
    /**
     * Ensures single JSON-RPC messages are serialized with a trailing newline when sent.
     */
    public function testSendWritesEncodedMessageWithNewline(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        $transport = new StdioTransport($input, $output);

        $message = new JsonRpcMessage('42', 'tools/list', ['batch' => false]);
        $transport->send($message);

        rewind($output);
        $written = stream_get_contents($output);

        self::assertSame(JsonCodec::encode($message) . "\n", $written);
    }

    /**
     * Verifies batches are encoded as a single JSON array with a newline terminator.
     */
    public function testSendBatchWritesJsonArray(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        $transport = new StdioTransport($input, $output);

        $messages = [
            new JsonRpcMessage('1', 'tools/call', ['name' => 'echo', 'args' => []]),
            new JsonRpcMessage('2', 'prompts/list'),
        ];

        $transport->sendBatch($messages);

        rewind($output);
        $written = stream_get_contents($output);

        self::assertSame(JsonCodec::encodeBatch($messages) . "\n", $written);
    }

    /**
     * Confirms cancel requests are emitted with the expected method name and payload.
     */
    public function testCancelEnqueuesCancellationNotification(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        $transport = new StdioTransport($input, $output);

        $transport->cancel('abc', 'tools/call');

        rewind($output);
        $written = stream_get_contents($output);
        $decoded = JsonCodec::decode(trim((string) $written));

        self::assertInstanceOf(JsonRpcMessage::class, $decoded);
        self::assertNull($decoded->id);
        self::assertSame('mcp.cancel', $decoded->method);
        self::assertSame(['id' => 'abc', 'method' => 'tools/call'], $decoded->params);
    }

    /**
     * Ensures newline-delimited and batched payloads are yielded in order while skipping blanks.
     */
    public function testIncomingStreamsMessagesAndBatches(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');

        $single = new JsonRpcMessage('10', 'session/ready', ['ok' => true]);
        $batch = [
            new JsonRpcMessage('11', 'resources/list'),
            new JsonRpcMessage(null, 'notifications/progress', ['step' => 2]),
        ];

        fwrite($input, JsonCodec::encode($single) . "\n");
        fwrite($input, "\n");
        fwrite($input, JsonCodec::encodeBatch($batch) . "\n");
        rewind($input);

        $transport = new StdioTransport($input, $output);
        $messages = $this->collectMessages($transport->incoming());

        self::assertCount(3, $messages);
        self::assertSame('session/ready', $messages[0]->method);
        self::assertSame('resources/list', $messages[1]->method);
        self::assertSame('notifications/progress', $messages[2]->method);
        self::assertSame(['step' => 2], $messages[2]->params);
    }

    /**
     * Verifies closing the transport releases the provided stream handles.
     */
    public function testCloseReleasesResources(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        $transport = new StdioTransport($input, $output);

        $transport->open();
        $transport->close();

        self::assertFalse(is_resource($input));
        self::assertFalse(is_resource($output));
    }

    /**
     * Collects JsonRpcMessage instances from the incoming iterable, flattening batch entries.
     *
     * @param iterable<int, JsonRpcMessage|array<int, JsonRpcMessage>> $incoming
     *
     * @return list<JsonRpcMessage>
     */
    private function collectMessages(iterable $incoming): array
    {
        $collected = [];

        foreach ($incoming as $item) {
            if (is_array($item)) {
                array_push($collected, ...$item);
                continue;
            }

            $collected[] = $item;
        }

        return $collected;
    }
}
