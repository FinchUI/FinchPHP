<?php

/**
 * Finch\Helper\Utility - 通用工具函数
 *
 * 提供 URL 构建、文件操作、日期格式化等通用工具方法。
 * 与 Security.php 分离，避免单文件膨胀。
 */

declare(strict_types=1);

namespace Finch\Helper;

final class Utility
{
    /**
     * 构建带查询参数的 URL。
     *
     * @param string $baseUrl 基础 URL
     * @param array<string, string|int> $params 查询参数
     */
    public static function buildUrl(string $baseUrl, array $params = []): string
    {
        if ($params === []) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($params);
    }

    /**
     * 确保路径以斜杠结尾。
     */
    public static function trailingSlash(string $path): string
    {
        return rtrim($path, '/') . '/';
    }

    /**
     * 移除路径末尾的斜杠。
     */
    public static function noTrailingSlash(string $path): string
    {
        return rtrim($path, '/');
    }

    /**
     * 安全拼接路径片段（避免重复斜杠）。
     */
    public static function joinPath(string ...$parts): string
    {
        $result = '';
        foreach ($parts as $part) {
            $part = trim($part, '/');
            if ($part === '') {
                continue;
            }
            $result .= '/' . $part;
        }

        return $result ?: '/';
    }

    /**
     * 人类可读的文件大小。
     */
    public static function humanFileSize(int $bytes, int $decimals = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log(max(1, $bytes), 1024));
        if ($factor >= count($units)) {
            $factor = count($units) - 1;
        }

        return round($bytes / pow(1024, $factor), $decimals) . ' ' . $units[(int) $factor];
    }

    /**
     * 格式化日期为站点时区。
     *
     * @param string $utcDateTime UTC 日期时间字符串（Y-m-d H:i:s）
     * @param string $format PHP 日期格式
     * @param string|null $timezone 目标时区（null 则使用站点设置）
     */
    public static function formatDate(string $utcDateTime, string $format = 'Y-m-d H:i', ?string $timezone = null): string
    {
        if ($utcDateTime === '' || $utcDateTime === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            $tz = $timezone ?? self::siteTimezone();
            $dt = new \DateTime($utcDateTime, new \DateTimeZone('UTC'));
            $dt->setTimezone(new \DateTimeZone($tz));

            return $dt->format($format);
        } catch (\Throwable) {
            return $utcDateTime;
        }
    }

    /**
     * 获取相对时间描述（如"3 分钟前"）。
     */
    public static function timeAgo(string $utcDateTime): string
    {
        if ($utcDateTime === '' || $utcDateTime === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            $dt = new \DateTime($utcDateTime, new \DateTimeZone('UTC'));
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $diff = $now->getTimestamp() - $dt->getTimestamp();

            if ($diff < 0) {
                return '-';
            }

            if ($diff < 60) {
                return $diff . ' 秒前';
            }

            if ($diff < 3600) {
                return floor($diff / 60) . ' 分钟前';
            }

            if ($diff < 86400) {
                return floor($diff / 3600) . ' 小时前';
            }

            if ($diff < 2592000) {
                return floor($diff / 86400) . ' 天前';
            }

            return self::formatDate($utcDateTime);
        } catch (\Throwable) {
            return $utcDateTime;
        }
    }

    /**
     * 获取站点时区（从 settings 中读取）。
     */
    public static function siteTimezone(): string
    {
        $app = \Finch\App::getInstance();
        $tz = (string) $app->settings->get('site_timezone', 'Asia/Shanghai');

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Asia/Shanghai';
    }

    /**
     * 生成 slug：小写、空格转连字符、去除特殊字符。
     */
    public static function slugify(string $text, string $fallback = 'post'): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? '';
        $text = preg_replace('/[^\p{L}\p{N}-]+/u', '-', $text) ?? '';
        $text = trim($text, '-');
        if ($text === '') {
            $text = $fallback;
        }

        return mb_substr($text, 0, 180);
    }

    /**
     * 生成随机文件名（保留扩展名）。
     */
    public static function randomFilename(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? '.' . strtolower($ext) : '';
        $date = date('Ymd');
        $hash = bin2hex(random_bytes(8));

        return $date . '_' . $hash . $ext;
    }

    /**
     * 安全读取文件内容（带大小限制）。
     */
    public static function safeFileGetContents(string $path, int $maxBytes = 10485760): string|false
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $size = filesize($path);
        if ($size === false || $size > $maxBytes) {
            return false;
        }

        return file_get_contents($path);
    }

    /**
     * 递归创建目录。
     */
    public static function ensureDirectory(string $path, int $mode = 0755): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $mode, true);
    }

    /**
     * 截取文本（按字符数，不截断 UTF-8 多字节字符）。
     */
    public static function truncate(string $text, int $length = 200, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * 检查扩展名是否在上传白名单中。
     */
    public static function isAllowedExtension(string $extension): bool
    {
        $allowed = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'zip', 'rar', '7z',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'md',
            'mp3', 'mp4', 'wav', 'ogg', 'webm',
        ];

        return in_array(strtolower($extension), $allowed, true);
    }

    /**
     * 获取 MIME 类型对应的通用文件类型标识。
     */
    public static function fileTypeFromMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (in_array($mime, [
            'application/zip', 'application/x-rar-compressed',
            'application/x-7z-compressed', 'application/gzip',
        ], true)) {
            return 'archive';
        }

        return 'document';
    }
}
