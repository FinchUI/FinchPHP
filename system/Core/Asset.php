<?php

/**
 * Finch\Core\Asset - 静态资源 URL 生成
 */

declare(strict_types=1);

namespace Finch\Core;

final class Asset
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function url(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $siteUrl = (string) $this->settings->get('site_url', '');

        if ($siteUrl === '') {
            return $path;
        }

        return rtrim($siteUrl, '/') . $path;
    }

    public function systemScript(string $file): string
    {
        return $this->url('/system/script/' . ltrim($file, '/'));
    }

    public function systemAsset(string $path): string
    {
        return $this->url('/system/assets/' . ltrim($path, '/'));
    }

    public function themeAsset(string $path, ?string $theme = null): string
    {
        $theme = $theme ?: (string) $this->settings->get('active_theme', 'default');

        return $this->url('/content/themes/' . $theme . '/assets/' . ltrim($path, '/'));
    }

    public function pluginAsset(string $plugin, string $path): string
    {
        return $this->url('/content/plugins/' . trim($plugin, '/') . '/assets/' . ltrim($path, '/'));
    }
}
