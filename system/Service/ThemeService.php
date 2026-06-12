<?php

/**
 * Finch\Service\ThemeService - 主题发现与切换
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Settings;
use Finch\Model\SystemSetting;

final class ThemeService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly string $themeDir = FP_CONTENT_DIR . '/themes',
        private readonly ?Cache $cache = null,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function discover(): array
    {
        $active = $this->activeTheme();
        $items = [];

        foreach ($this->directories($this->themeDir) as $id => $path) {
            $meta = $this->metadata($id, $path);
            $meta['active'] = $id === $active;
            $items[] = $meta;
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return $items;
    }

    public function activeTheme(): string
    {
        return trim((string) $this->settings->get('active_theme', 'default')) ?: 'default';
    }

    public function activate(string $theme): bool
    {
        $theme = trim($theme);
        if ($theme === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return false;
        }

        if (!$this->exists($theme)) {
            return false;
        }

        SystemSetting::put('active_theme', $theme, 'string', true);
        $this->settings->set('active_theme', $theme);
        $this->cache?->flushGroups(['theme', 'home', 'post']);

        return true;
    }

    public function exists(string $theme): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return false;
        }

        return is_dir($this->themeDir . '/' . $theme);
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
        $meta = $this->json($path . '/theme.json');

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
