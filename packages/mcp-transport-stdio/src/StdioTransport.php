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
        $this->in = $in ?? fopen('php://stdin', 'r');
        $this->out = $out ?? fopen('php://stdout', 'w');
    }

    public function open(): void
    {
        $this->open = true;
    }

    public function send(JsonRpcMessage $msg): void
    {
        $this->ensureOpen();
        $this->write($msg);
    }

    public function cancel(string $id, string $method): void
    {
        $this->ensureOpen();
        $this->write(new JsonRpcMessage(null, 'mcp.cancel', ['id' => $id, 'method' => $method]));
    }

    /** @return iterable<JsonRpcMessage> */
    public function incoming(): iterable
    {
        $this->ensureOpen();

        while (!feof($this->in)) {
            $line = fgets($this->in);
            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            yield JsonCodec::decode($line);
        }
    }

    public function close(): void
    {
        $this->open = false;
        if (is_resource($this->in)) {
            fclose($this->in);
        }
        if (is_resource($this->out)) {
            fclose($this->out);
        }
    }

    private function ensureOpen(): void
    {
        if (!$this->open) {
            $this->open();
        }
    }

    private function write(JsonRpcMessage $msg): void
    {
        fwrite($this->out, JsonCodec::encode($msg) . "\n");
        fflush($this->out);
    }
}
