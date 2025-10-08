<?php
declare(strict_types=1);
namespace AntonBrutto\McpTransportStreamHttp;
use AntonBrutto\McpCodecJson\JsonCodec;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** Streamable HTTP transport supporting POST requests and server-sent events for responses. */
final class StreamHttpTransport implements TransportInterface
{
    /** Active stream for server push responses. */
    private ?StreamInterface $stream = null;

    /** Accumulator for partial chunks read from the stream. */
    private string $buffer = '';

    /** Tracks whether the current event buffer is collected from SSE data frames. */
    private bool $usingSse = false;

    /** Aggregated payload for the current SSE event. */
    private string $eventBuffer = '';

    public function __construct(
        private readonly string $endpoint,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $reqFactory,
        private readonly ?string $streamEndpoint = null
    ) {
    }

    public function open(): void {}

    public function send(JsonRpcMessage $msg): void
    {
        $this->dispatch($msg);
    }

    public function sendBatch(array $messages): void
    {
        $this->dispatchBatch($messages);
    }

    public function cancel(string $id, string $method): void
    {
        $this->dispatch(new JsonRpcMessage(null, 'mcp.cancel', ['id' => $id, 'method' => $method]));
    }

    /** @return iterable<JsonRpcMessage|array<int,JsonRpcMessage>> */
    public function incoming(): iterable
    {
        $stream = $this->openStream();

        while (true) {
            if ($stream->eof()) {
                $message = $this->consumeEventBuffer();
                if ($message !== null) {
                    yield $message;
                }
                $this->stream = null;

                return;
            }

            $chunk = $stream->read(8192);
            if ($chunk === '') {
                usleep(10_000);
                continue;
            }

            $this->buffer .= $chunk;

            while (($pos = strpos($this->buffer, "\n")) !== false) {
                $line = rtrim(substr($this->buffer, 0, $pos), "\r");
                $this->buffer = substr($this->buffer, $pos + 1);

                if ($line === '') {
                    $message = $this->consumeEventBuffer();
                    if ($message !== null) {
                        yield $message;
                    }
                    $this->usingSse = false;
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $this->usingSse = true;
                    $this->eventBuffer .= ltrim(substr($line, 5));
                    continue;
                }

                if ($this->usingSse) {
                    continue; // ignore other SSE fields
                }

                if ($line === '[DONE]') {
                    continue;
                }

                $decoded = JsonCodec::decode($line);
                yield $decoded;
            }
        }
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            $this->stream->close();
            $this->stream = null;
        }
    }

    private function dispatch(JsonRpcMessage $msg): void
    {
        $req = $this->reqFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Content-Type', 'application/json');
        $req->getBody()->write(JsonCodec::encode($msg));
        $this->http->sendRequest($req);
    }

    /** @param array<int,JsonRpcMessage> $messages */
    private function dispatchBatch(array $messages): void
    {
        $req = $this->reqFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Content-Type', 'application/json');
        $req->getBody()->write(JsonCodec::encodeBatch($messages));
        $this->http->sendRequest($req);
    }

    private function openStream(): StreamInterface
    {
        if ($this->stream !== null && !$this->stream->eof()) {
            return $this->stream;
        }

        $target = $this->streamEndpoint ?? $this->endpoint;
        $req = $this->reqFactory->createRequest('GET', $target)
            ->withHeader('Accept', 'text/event-stream');
        $resp = $this->http->sendRequest($req);
        $code = $resp->getStatusCode();

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('Failed to open stream endpoint, status ' . $code);
        }

        $this->stream = $resp->getBody();
        $this->buffer = '';
        $this->usingSse = false;
        $this->eventBuffer = '';

        return $this->stream;
    }

    private function consumeEventBuffer(): JsonRpcMessage|array|null
    {
        if ($this->eventBuffer === '') {
            return null;
        }

        $payload = $this->eventBuffer;
        $this->eventBuffer = '';

        return JsonCodec::decode($payload);
    }
}
