<?php

/**
 * Finch\Service\ModuleService - 官方模块发现与启停
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Model\ModuleSetting;

final class ModuleService
{
    public function __construct(
        private readonly string $moduleDir = FP_CONTENT_DIR . '/modules',
        private readonly ?Cache $cache = null,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function discover(): array
    {
        $items = [];

        foreach ($this->directories($this->moduleDir) as $id => $path) {
            $meta = $this->metadata($id, $path);
            $meta['enabled'] = $this->isEnabled($id);
            $items[] = $meta;
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return $items;
    }

    public function isEnabled(string $module): bool
    {
        $value = ModuleSetting::value($module, 'enabled');
        if ($value === null) {
            return true;
        }

        return $value === '1' || strtolower($value) === 'true';
    }

    public function setEnabled(string $module, bool $enabled): bool
    {
        if (!$this->exists($module)) {
            return false;
        }

        ModuleSetting::put($module, 'enabled', $enabled, 'bool', true);
        $this->cache?->flushGroups(['module', 'home', 'post']);

        return true;
    }

    public function exists(string $module): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $module)) {
            return false;
        }

        return is_dir($this->moduleDir . '/' . $module);
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
        $meta = $this->json($path . '/module.json');

        return [
            'id' => $id,
            'name' => $this->stringOr($meta, 'name', $id),
            'version' => $this->stringOr($meta, 'version', '1.0.0'),
            'description' => $this->stringOr($meta, 'description', ''),
            'author' => $this->stringOr($meta, 'author', 'Finch'),
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
}
