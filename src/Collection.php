<?php
namespace Fzr;

/**
 * Collectionクラス
 */
class Collection implements \Countable, \IteratorAggregate, \ArrayAccess {
    protected array $items;

    public function __construct(array $items = []) {
        $this->items = $items;
    }

    public function all(): array { return $this->items; }
    public function count(): int { return count($this->items); }
    public function isEmpty(): bool { return empty($this->items); }
    public function isNotEmpty(): bool { return !empty($this->items); }
    public function first(mixed $default = null): mixed { return $this->items[array_key_first($this->items)] ?? $default; }
    public function last(mixed $default = null): mixed { return $this->items[array_key_last($this->items)] ?? $default; }

    public function map(callable $callback): self {
        return new self(array_map($callback, $this->items));
    }

    public function filter(callable $callback): self {
        return new self(array_values(array_filter($this->items, $callback)));
    }

    public function each(callable $callback): self {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) break;
        }
        return $this;
    }

    public function pluck(string $key): self {
        $result = [];
        foreach ($this->items as $item) {
            if (is_array($item) && array_key_exists($key, $item)) $result[] = $item[$key];
            elseif (is_object($item) && property_exists($item, $key)) $result[] = $item->$key;
        }
        return new self($result);
    }

    public function groupBy(string $key): self {
        $groups = [];
        foreach ($this->items as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? '') : ($item->$key ?? '');
            $groups[$groupKey][] = $item;
        }
        return new self($groups);
    }

    public function sortBy(string $key, bool $desc = false): self {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $desc) {
            $va = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
            $vb = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);
            return $desc ? ($vb <=> $va) : ($va <=> $vb);
        });
        return new self($items);
    }

    public function unique(?string $key = null): self {
        if ($key === null) return new self(array_values(array_unique($this->items)));
        $seen = [];
        $result = [];
        foreach ($this->items as $item) {
            $val = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if (!in_array($val, $seen, true)) {
                $seen[] = $val;
                $result[] = $item;
            }
        }
        return new self($result);
    }

    public function contains(mixed $value): bool {
        return in_array($value, $this->items, true);
    }

    public function sum(?string $key = null): int|float {
        if ($key === null) return array_sum($this->items);
        return array_sum($this->pluck($key)->all());
    }

    public function max(?string $key = null): mixed {
        if (empty($this->items)) return null;
        if ($key === null) return max($this->items);
        return max($this->pluck($key)->all());
    }

    public function min(?string $key = null): mixed {
        if (empty($this->items)) return null;
        if ($key === null) return min($this->items);
        return min($this->pluck($key)->all());
    }

    public function avg(?string $key = null): float|int|null {
        $count = $this->count();
        return $count > 0 ? ($this->sum($key) / $count) : null;
    }

    public function toArray(): array { return $this->items; }
    public function toJson(int $options = 0): string { return json_encode($this->items, $options | JSON_UNESCAPED_UNICODE); }
    public function getIterator(): \ArrayIterator { return new \ArrayIterator($this->items); }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->items[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $offset === null ? $this->items[] = $value : $this->items[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->items[$offset]); }
}
