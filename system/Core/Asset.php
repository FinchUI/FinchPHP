<?php

/**
 * Finch\Core\Asset - 静态资源 URL 生成（含缓存破坏）
 */

declare(strict_types=1);

namespace Finch\Core;

final class Asset
{
    /** @var array<string, string> mtime 缓存，避免同请求重复 filemtime */
    private array $mtimeCache = [];

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
        $relPath = '/system/script/' . ltrim($file, '/');

        return $this->url($relPath) . $this->versionParam($relPath);
    }

    public function systemAsset(string $path): string
    {
        $relPath = '/system/assets/' . ltrim($path, '/');

        return $this->url($relPath) . $this->versionParam($relPath);
    }

    public function themeAsset(string $path, ?string $theme = null): string
    {
        $theme = $theme ?: (string) $this->settings->get('active_theme', 'default');
        $relPath = '/content/themes/' . $theme . '/assets/' . ltrim($path, '/');

        return $this->url($relPath) . $this->versionParam($relPath);
    }

    public function pluginAsset(string $plugin, string $path): string
    {
        $relPath = '/content/plugins/' . trim($plugin, '/') . '/assets/' . ltrim($path, '/');

        return $this->url($relPath) . $this->versionParam($relPath);
    }

    /**
     * 根据文件修改时间生成 ?v= 参数
     */
    private function versionParam(string $relPath): string
    {
        if (isset($this->mtimeCache[$relPath])) {
            return '?v=' . $this->mtimeCache[$relPath];
        }

        // 项目根目录 + 相对路径
        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        $fsPath = $docRoot . $relPath;

        // 尝试从主题/插件 JSON 读取版本号（优先）
        $version = $this->readJsonVersion($relPath);
        if ($version !== '') {
            $this->mtimeCache[$relPath] = $version;

            return '?v=' . $version;
        }

        // 回退到文件修改时间
        $mtime = @filemtime($fsPath);
        if ($mtime !== false) {
            $this->mtimeCache[$relPath] = (string) $mtime;

            return '?v=' . $mtime;
        }

        // 都取不到则用当前年月日（极少触发）
        $fallback = date('Ymd');
        $this->mtimeCache[$relPath] = $fallback;

        return '?v=' . $fallback;
    }

    /**
     * 从 theme.json / plugin.json 读取版本号
     */
    private function readJsonVersion(string $relPath): string
    {
        // 主题资源：/content/themes/{theme}/assets/...
        if (preg_match('#^/content/themes/([^/]+)/#', $relPath, $m)) {
            $jsonPath = $this->docRoot() . '/content/themes/' . $m[1] . '/theme.json';

            return $this->readVersionFromJson($jsonPath);
        }

        // 插件资源：/content/plugins/{plugin}/assets/...
        if (preg_match('#^/content/plugins/([^/]+)/#', $relPath, $m)) {
            $jsonPath = $this->docRoot() . '/content/plugins/' . $m[1] . '/plugin.json';

            return $this->readVersionFromJson($jsonPath);
        }

        return '';
    }

    private function readVersionFromJson(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['version'])) {
            return '';
        }

        return (string) $data['version'];
    }

    private function docRoot(): string
    {
        return (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    }
}
