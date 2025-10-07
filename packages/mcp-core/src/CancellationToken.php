<?php
declare(strict_types=1);

namespace AntonBrutto\McpCore;

final class CancellationToken
{
    private bool $cancelled = false;

    private function __construct(private readonly ?float $deadline)
    {
    }

    public static function none(): self
    {
        return new self(null);
    }

    public static function fromTimeout(float $seconds): self
    {
        return new self(microtime(true) + $seconds);
    }

    public static function withDeadline(float $unixTimestamp): self
    {
        return new self($unixTimestamp);
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        if ($this->cancelled) {
            return true;
        }

        if ($this->deadline !== null && microtime(true) >= $this->deadline) {
            $this->cancelled = true;
        }

        return $this->cancelled;
    }

    public function remainingSeconds(): ?float
    {
        if ($this->deadline === null) {
            return null;
        }

        $remaining = $this->deadline - microtime(true);

        return $remaining > 0 ? $remaining : 0.0;
    }
}
