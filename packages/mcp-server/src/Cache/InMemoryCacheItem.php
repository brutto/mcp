<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;

/** Lightweight cache item for the in-memory PSR-6 pool. */
final class InMemoryCacheItem implements CacheItemInterface
{
    private mixed $value = null;

    private bool $hit = false;

    private ?DateTimeImmutable $expiresAt = null;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        if (!$this->hit) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt <= new DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration;

        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expiresAt = (new DateTimeImmutable())->add($time);
        } else {
            $this->expiresAt = (new DateTimeImmutable())->modify('+' . $time . ' seconds');
        }

        return $this;
    }

    /** @internal */
    public function markHit(mixed $value, ?DateTimeImmutable $expiresAt): void
    {
        $this->value = $value;
        $this->expiresAt = $expiresAt;
        $this->hit = true;
    }

    /** @internal */
    public function rawExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
