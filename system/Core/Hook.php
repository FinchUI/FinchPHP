<?php

/**
 * Finch\Core\Hook - 钩子/插件系统
 *
 * 继承 Z-Blog 模式（见 PROJECT_PLAN §4.6）：
 *   - add($name, $callback, $priority)  注册钩子
 *   - do($name, ...$args)               触发动作钩子（无返回值）
 *   - filter($name, $value, ...$args)   过滤器钩子（链式传递并返回 $value）
 *
 * 命名规范：fp_{context}_{action}，如 fp_init / fp_post_save。
 */

declare(strict_types=1);

namespace Finch\Core;

final class Hook
{
    /**
     * 已注册的回调，按钩子名分组。
     * 结构：[ name => [ priority => [callable, callable, ...] ] ]
     *
     * @var array<string, array<int, list<callable>>>
     */
    private array $listeners = [];

    /**
     * 注册钩子回调。
     *
     * @param int $priority 数字越小越先执行（默认 10）
     */
    public function add(string $name, callable $callback, int $priority = 10): void
    {
        $this->listeners[$name][$priority][] = $callback;
    }

    /** 是否存在某钩子的回调 */
    public function has(string $name): bool
    {
        return !empty($this->listeners[$name]);
    }

    /** 移除某钩子的全部回调 */
    public function remove(string $name): void
    {
        unset($this->listeners[$name]);
    }

    /**
     * 触发动作钩子，依优先级顺序执行所有回调，无返回值。
     */
    public function do(string $name, mixed ...$args): void
    {
        foreach ($this->sortedCallbacks($name) as $callback) {
            $callback(...$args);
        }
    }

    /**
     * 触发过滤器钩子：$value 在回调间链式传递，每个回调返回新值。
     *
     * @return mixed 经全部回调处理后的最终值
     */
    public function filter(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sortedCallbacks($name) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    /**
     * 返回某钩子按优先级排序后的回调列表。
     *
     * @return list<callable>
     */
    private function sortedCallbacks(string $name): array
    {
        if (empty($this->listeners[$name])) {
            return [];
        }

        $byPriority = $this->listeners[$name];
        ksort($byPriority);

        $flat = [];
        foreach ($byPriority as $callbacks) {
            foreach ($callbacks as $callback) {
                $flat[] = $callback;
            }
        }

        return $flat;
    }
}
