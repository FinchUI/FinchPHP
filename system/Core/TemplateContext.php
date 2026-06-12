<?php

/**
 * Finch\Core\TemplateContext - 模板上下文栈
 */

declare(strict_types=1);

namespace Finch\Core;

final class TemplateContext
{
    /** @var list<mixed> */
    private array $stack = [];

    /** @var array<string, mixed> */
    private array $shared = [];

    public function push(mixed $value): void
    {
        $this->stack[] = $value;
    }

    public function pop(): mixed
    {
        return array_pop($this->stack);
    }

    public function current(): mixed
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    public function peek(int $depth = 0): mixed
    {
        $index = count($this->stack) - 1 - max(0, $depth);
        if ($index < 0 || !array_key_exists($index, $this->stack)) {
            return null;
        }

        return $this->stack[$index];
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function clear(): void
    {
        $this->stack = [];
        $this->shared = [];
    }

    public function set(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->shared);
    }

    public function remove(string $key): void
    {
        unset($this->shared[$key]);
    }

    /** @return array<string, mixed> */
    public function shared(): array
    {
        return $this->shared;
    }
}
