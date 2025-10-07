<?php
declare(strict_types=1);

namespace AntonBrutto\McpClient\Internal;

use AntonBrutto\McpCore\CancellationException;
use AntonBrutto\McpCore\CancellationToken;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCore\TimeoutException;
use Closure;
use RuntimeException;

/** Tracks an in-flight JSON-RPC request and surfaces its eventual response or failure. */
final class Deferred
{
    /** Captured response payload when resolved. */
    private ?JsonRpcMessage $message = null;

    /** Captured error when rejected. */
    private ?RuntimeException $failure = null;

    /** Indicates the deferred has already been resolved or rejected. */
    private bool $settled = false;

    /** Guards against invoking the cancel hook more than once. */
    private bool $cancelSignalled = false;

    public function __construct(
        /** JSON-RPC id associated with the request. */
        private readonly string $id,
        /** Method name for diagnostic output. */
        private readonly string $method,
        /** Token used to detect user-driven cancellation. */
        private readonly ?CancellationToken $token,
        /** Absolute microsecond timestamp after which the request times out. */
        private readonly ?float $deadline,
        /** Optional hook for notifying transport/server about cancellation. */
        private readonly ?Closure $onCancel = null
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isSettled(): bool
    {
        return $this->settled;
    }

    public function resolve(JsonRpcMessage $message): void
    {
        if ($this->settled) {
            return;
        }

        $this->message = $message;
        $this->settled = true;
    }

    public function reject(RuntimeException $error): void
    {
        if ($this->settled) {
            return;
        }

        $this->failure = $error;
        $this->settled = true;
    }

    public function message(): JsonRpcMessage
    {
        if (!$this->settled) {
            throw new RuntimeException('Deferred is not resolved yet.');
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        if ($this->message === null) {
            throw new RuntimeException('Deferred was settled without value.');
        }

        return $this->message;
    }

    public function guard(): void
    {
        if ($this->settled) {
            return;
        }

        if ($this->token?->isCancelled()) {
            $this->signalCancel();
            $this->reject(new CancellationException(sprintf(
                'Request %s (%s) was cancelled.',
                $this->id,
                $this->method
            )));

            return;
        }

        if ($this->deadline !== null && microtime(true) >= $this->deadline) {
            $this->signalCancel();
            $this->reject(new TimeoutException(sprintf(
                'Request %s (%s) timed out.',
                $this->id,
                $this->method
            )));
        }
    }

    private function signalCancel(): void
    {
        if ($this->cancelSignalled || $this->onCancel === null) {
            return;
        }

        $this->cancelSignalled = true;
        ($this->onCancel)();
    }
}
