<?php
declare(strict_types=1);
namespace AntonBrutto\McpTransportStdio;
use AntonBrutto\McpCodecJson\JsonCodec;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TransportInterface;

final class StdioTransport implements TransportInterface
{
    private $in;

    private $out;

    private bool $open = false;

    public function __construct($in = null, $out = null)
    {
        $this->in = $in;
        $this->out = $out;
    }

    public function open(): void
    {
        if ($this->open) {
            return;
        }

        if (!is_resource($this->in)) {
            $this->in = fopen('php://stdin', 'r');
        }

        if (!is_resource($this->out)) {
            $this->out = fopen('php://stdout', 'w');
        }

        $this->open = true;
    }

    public function send(JsonRpcMessage $msg): void
    {
        $this->ensureOpen();
        $this->write($msg);
    }

    public function sendBatch(array $messages): void
    {
        $this->ensureOpen();
        if (!is_resource($this->out)) {
            return;
        }
        fwrite($this->out, JsonCodec::encodeBatch($messages) . "\n");
        fflush($this->out);
    }

    public function cancel(string $id, string $method): void
    {
        $this->ensureOpen();
        $this->write(new JsonRpcMessage(null, 'mcp.cancel', ['id' => $id, 'method' => $method]));
    }

    /** @return iterable<JsonRpcMessage|array<int,JsonRpcMessage>> */
    public function incoming(): iterable
    {
        $this->ensureOpen();

        while ($this->open && is_resource($this->in) && !feof($this->in)) {
            $line = fgets($this->in);
            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = JsonCodec::decode($line);
            if (is_array($decoded)) {
                yield $decoded;
                continue;
            }

            yield $decoded;
        }
    }

    public function close(): void
    {
        if (!$this->open) {
            return;
        }

        $this->open = false;

        if (is_resource($this->in)) {
            fclose($this->in);
        }
        if (is_resource($this->out)) {
            fflush($this->out);
            fclose($this->out);
        }

        $this->in = null;
        $this->out = null;
    }

    private function ensureOpen(): void
    {
        if (!$this->open) {
            $this->open();
        }
    }

    private function write(JsonRpcMessage $msg): void
    {
        if (!is_resource($this->out)) {
            return;
        }

        fwrite($this->out, JsonCodec::encode($msg) . "\n");
        fflush($this->out);
    }
}
