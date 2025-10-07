<?php
declare(strict_types=1);

namespace AntonBrutto\McpServer\Cache;

use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/** Simple in-memory cache pool suitable for tests and examples. */
final class InMemoryCachePool implements CacheItemPoolInterface
{
    /** @var array<string,array{value:mixed,expiresAt:?DateTimeImmutable}> */
    private array $store = [];

    /** @var array<string,InMemoryCacheItem> */
    private array $deferred = [];

    public function getItem(string $key): CacheItemInterface
    {
        $item = new InMemoryCacheItem($key);

        if (isset($this->store[$key])) {
            $entry = $this->store[$key];
            if ($entry['expiresAt'] === null || $entry['expiresAt'] > new DateTimeImmutable()) {
                $item->markHit($entry['value'], $entry['expiresAt']);
            } else {
                unset($this->store[$key]);
            }
        }

        return $item;
    }

    public function getItems(array $keys = []): iterable
    {
        if ($keys === []) {
            return [];
        }

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        $expiresAt = $this->store[$key]['expiresAt'];
        if ($expiresAt !== null && $expiresAt <= new DateTimeImmutable()) {
            unset($this->store[$key]);

            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        $this->deferred = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->store[$key], $this->deferred[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof InMemoryCacheItem) {
            $wrapped = new InMemoryCacheItem($item->getKey());
            $wrapped->set($item->get());
            $item = $wrapped;
        }

        $this->store[$item->getKey()] = [
            'value'     => $item->get(),
            'expiresAt' => $item->rawExpiresAt(),
        ];

        unset($this->deferred[$item->getKey()]);

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof InMemoryCacheItem) {
            $wrapped = new InMemoryCacheItem($item->getKey());
            $wrapped->set($item->get());
            $item = $wrapped;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->save($item);
        }

        $this->deferred = [];

        return true;
    }
}
