<?php

/**
 * Finch\Service\PluginService - 插件元数据发现
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\App;
use Finch\Core\Cache;
use Finch\Core\Logger;
use Finch\Model\PluginSetting;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

final class PluginService
{
    public function __construct(
        private readonly string $pluginDir = FP_CONTENT_DIR . '/plugins',
        private readonly ?Cache $cache = null,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function discover(): array
    {
        $items = [];

        foreach ($this->directories($this->pluginDir) as $id => $path) {
            $meta = $this->metadata($id, $path);
            $meta['enabled'] = $this->isEnabled($id, (bool) ($meta['preinstalled'] ?? false));
            $meta['has_runtime'] = is_file($path . '/include.php');
            $items[] = $meta;
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return $items;
    }

    public function exists(string $plugin): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $plugin)) {
            return false;
        }

        return is_dir($this->pluginDir . '/' . $plugin);
    }

    public function isEnabled(string $plugin, bool $defaultEnabled = true): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $plugin)) {
            return false;
        }

        $value = PluginSetting::value($plugin, 'enabled');
        if ($value === null) {
            return $defaultEnabled;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function setEnabled(string $plugin, bool $enabled): bool
    {
        if (!$this->exists($plugin)) {
            return false;
        }

        PluginSetting::put($plugin, 'enabled', $enabled, 'bool', true);
        $this->cache?->flushGroups(['plugin', 'home', 'post', 'search', 'comment']);

        return true;
    }

    /**
     * 加载已启用插件的 include.php。
     *
     * @return array{loaded:list<string>,disabled:list<string>,missing_runtime:list<string>,errors:array<string,string>}
     */
    public function loadEnabled(?Logger $logger = null): array
    {
        $loaded = [];
        $disabled = [];
        $missingRuntime = [];
        $errors = [];

        foreach ($this->directories($this->pluginDir) as $id => $path) {
            $meta = $this->metadata($id, $path);
            $enabled = $this->isEnabled($id, (bool) ($meta['preinstalled'] ?? false));

            if (!$enabled) {
                $disabled[] = $id;

                continue;
            }

            $includeFile = $path . '/include.php';
            if (!is_file($includeFile)) {
                $missingRuntime[] = $id;

                continue;
            }

            try {
                $bootstrap = require $includeFile;
                $this->invokePluginBootstrap($bootstrap, $meta);
                $loaded[] = $id;
            } catch (Throwable $e) {
                $errors[$id] = $e->getMessage();
                $logger?->error('插件加载失败：' . $id . ' - ' . $e->getMessage(), [
                    'plugin' => $id,
                    'file' => $includeFile,
                ]);
            }
        }

        return [
            'loaded' => $loaded,
            'disabled' => $disabled,
            'missing_runtime' => $missingRuntime,
            'errors' => $errors,
        ];
    }

    /** @return array<string,string> */
    private function directories(string $base): array
    {
        if (!is_dir($base)) {
            return [];
        }

        $result = [];
        $names = scandir($base);
        if (!is_array($names)) {
            return [];
        }

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $base . '/' . $name;
            if (!is_dir($path) || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                continue;
            }

            $result[$name] = $path;
        }

        ksort($result);

        return $result;
    }

    /** @return array<string,mixed> */
    private function metadata(string $id, string $path): array
    {
        $meta = $this->json($path . '/plugin.json');

        return [
            'id' => $id,
            'name' => $this->stringOr($meta, 'name', $id),
            'version' => $this->stringOr($meta, 'version', '1.0.0'),
            'description' => $this->stringOr($meta, 'description', ''),
            'author' => $this->stringOr($meta, 'author', 'Finch'),
            'settings_page' => $this->stringOr($meta, 'settings_page', ''),
            'menu_location' => $this->stringOr($meta, 'menu_location', 'hidden'),
            'preinstalled' => (bool) ($meta['preinstalled'] ?? false),
            'path' => $path,
        ];
    }

    /** @return array<string,mixed> */
    private function json(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $meta */
    private function stringOr(array $meta, string $key, string $fallback): string
    {
        return isset($meta[$key]) && is_scalar($meta[$key]) ? trim((string) $meta[$key]) : $fallback;
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function invokePluginBootstrap(mixed $bootstrap, array $meta): void
    {
        if ($bootstrap === null || $bootstrap === 1) {
            return;
        }

        if (is_array($bootstrap) && isset($bootstrap['bootstrap']) && is_callable($bootstrap['bootstrap'])) {
            $this->invokeCallable($bootstrap['bootstrap'], [App::getInstance(), $meta]);

            return;
        }

        if (is_callable($bootstrap)) {
            $this->invokeCallable($bootstrap, [App::getInstance(), $meta]);
        }
    }

    /** @param list<mixed> $args */
    private function invokeCallable(callable $callback, array $args): void
    {
        $reflection = $this->reflectCallable($callback);
        $invokeArgs = $reflection->isVariadic()
            ? $args
            : array_slice($args, 0, $reflection->getNumberOfParameters());

        $callback(...$invokeArgs);
    }

    private function reflectCallable(callable $callback): ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], (string) $callback[1]);
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback, 2);

            return new ReflectionMethod($class, $method);
        }

        if (is_object($callback) && !$callback instanceof \Closure) {
            return new ReflectionMethod($callback, '__invoke');
        }

        return new ReflectionFunction($callback);
    }
}
