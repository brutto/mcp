<?php
declare(strict_types=1);

namespace AntonBrutto\McpTransportStreamHttpTests {

use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpTransportStreamHttp\StreamHttpTransport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @covers \AntonBrutto\McpTransportStreamHttp\StreamHttpTransport */
final class StreamHttpTransportTest extends TestCase
{
    /** Ensures newline-delimited JSON from the stream is decoded into JsonRpcMessage DTOs. */
    public function testIncomingParsesLineDelimitedMessages(): void
    {
        $streamBody = <<<JSON
{"jsonrpc":"2.0","id":"1","result":{"ok":true}}
{"jsonrpc":"2.0","method":"notify","params":{"x":1}}

JSON;

        $client = new FakeClient([
            new FakeResponse(200, new FakeStream($streamBody)),
        ]);
        $factory = new FakeRequestFactory();

        $transport = new StreamHttpTransport(
            'https://example.test/rpc',
            $client,
            $factory,
            'https://example.test/stream'
        );

        $raw = iterator_to_array($transport->incoming());
        $messages = [];
        foreach ($raw as $entry) {
            if (is_array($entry)) {
                array_push($messages, ...$entry);
            } else {
                $messages[] = $entry;
            }
        }

        self::assertCount(2, $messages);
        self::assertSame('1', $messages[0]->id);
        self::assertSame('notify', $messages[1]->method);
    }

    /** Verifies SSE payloads are coalesced across data frames with blank-line delimiters. */
    public function testIncomingParsesServerSentEvents(): void
    {
        $sseBody = <<<SSE
data: {"jsonrpc":"2.0","id":"11","result":{"done":true}}

data: {"jsonrpc":"2.0","method":"mcp.cancelled","params":{"id":"7"}}

[DONE]

SSE;

        $client = new FakeClient([
            new FakeResponse(200, new FakeStream($sseBody)),
        ]);
        $factory = new FakeRequestFactory();

        $transport = new StreamHttpTransport(
            'https://example.test/rpc',
            $client,
            $factory,
            'https://example.test/stream'
        );

        $raw = iterator_to_array($transport->incoming());
        $messages = [];
        foreach ($raw as $entry) {
            if (is_array($entry)) {
                array_push($messages, ...$entry);
            } else {
                $messages[] = $entry;
            }
        }

        self::assertCount(2, $messages);
        self::assertSame('11', $messages[0]->id);
        self::assertSame('mcp.cancelled', $messages[1]->method);
    }
}

final class FakeClient implements ClientInterface
{
    /** @var array<int, ResponseInterface> */
    private array $responses;

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($request->getMethod() === 'GET') {
            return array_shift($this->responses) ?? new FakeResponse(204, new FakeStream(''));
        }

        return new FakeResponse(204, new FakeStream(''));
    }
}

final class FakeRequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new FakeRequest($method, (string) $uri);
    }
}

final class FakeRequest implements RequestInterface
{
    private array $headers = [];

    private StreamInterface $body;

    public function __construct(private string $method, private string $uri)
    {
        $this->body = new FakeStream('');
    }

    public function getRequestTarget(): string
    {
        return $this->uri;
    }

    public function withRequestTarget($requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = (string) $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = (string) $method;

        return $clone;
    }

    public function getUri(): \Psr\Http\Message\UriInterface
    {
        return new FakeUri($this->uri);
    }

    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = (string) $uri;

        return $clone;
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion($version): RequestInterface
    {
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): RequestInterface
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = (array) $value;

        return $clone;
    }

    public function withAddedHeader($name, $value): RequestInterface
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)][] = $value;

        return $clone;
    }

    public function withoutHeader($name): RequestInterface
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): RequestInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }
}

final class FakeResponse implements ResponseInterface
{
    public function __construct(private int $statusCode, private StreamInterface $body)
    {
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion($version): ResponseInterface
    {
        return $this;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function hasHeader($name): bool
    {
        return false;
    }

    public function getHeader($name): array
    {
        return [];
    }

    public function getHeaderLine($name): string
    {
        return '';
    }

    public function withHeader($name, $value): ResponseInterface
    {
        return $this;
    }

    public function withAddedHeader($name, $value): ResponseInterface
    {
        return $this;
    }

    public function withoutHeader($name): ResponseInterface
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $clone = clone $this;
        $clone->statusCode = (int) $code;

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return '';
    }
}

final class FakeStream implements StreamInterface
{
    private int $position = 0;

    private bool $closed = false;

    public function __construct(private string $buffer)
    {
    }

    public function __toString(): string
    {
        return $this->buffer;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->buffer = '';
        $this->position = 0;
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->buffer);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->buffer);
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Not seekable');
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function write($string): int
    {
        $length = strlen((string) $string);
        $this->buffer .= (string) $string;

        return $length;
    }

    public function isReadable(): bool
    {
        return !$this->closed;
    }

    public function read($length): string
    {
        if ($this->closed || $length <= 0) {
            return '';
        }

        $chunk = substr($this->buffer, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $contents = substr($this->buffer, $this->position);
        $this->position = strlen($this->buffer);

        return $contents;
    }

    public function getMetadata($key = null): mixed
    {
        $meta = ['timed_out' => false, 'blocked' => false];

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}

final class FakeUri implements \Psr\Http\Message\UriInterface
{
    public function __construct(private string $uri)
    {
    }

    public function getScheme(): string
    {
        return parse_url($this->uri, PHP_URL_SCHEME) ?? '';
    }

    public function getAuthority(): string
    {
        return '';
    }

    public function getUserInfo(): string
    {
        return '';
    }

    public function getHost(): string
    {
        return parse_url($this->uri, PHP_URL_HOST) ?? '';
    }

    public function getPort(): ?int
    {
        return parse_url($this->uri, PHP_URL_PORT) ?: null;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '';
    }

    public function getQuery(): string
    {
        return parse_url($this->uri, PHP_URL_QUERY) ?? '';
    }

    public function getFragment(): string
    {
        return parse_url($this->uri, PHP_URL_FRAGMENT) ?? '';
    }

    public function withScheme($scheme): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withUserInfo($user, $password = null): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withHost($host): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withPort($port): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withPath($path): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withQuery($query): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function withFragment($fragment): \Psr\Http\Message\UriInterface
    {
        return $this;
    }

    public function __toString(): string
    {
        return $this->uri;
    }
}

}
