<?php

/**
 * Finch\Helper\Security - 安全过滤辅助函数
 *
 * 提供 XSS 清洗、SQL 转义、CSRF 令牌生成等安全相关工具函数。
 * 全局可调用，为模板和控制器提供便捷的安全方法。
 */

declare(strict_types=1);

namespace Finch\Helper;

final class Security
{
    /**
     * HTML 转义输出（防 XSS）。
     */
    public static function escHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * HTML 属性转义（防 XSS，额外处理单引号）。
     */
    public static function escAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * JavaScript 字符串转义（防 XSS）。
     */
    public static function escJs(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * URL 转义（防 URL 注入）。
     */
    public static function escUrl(string $url): string
    {
        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
            return '';
        }

        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }

    /**
     * 生成 CSRF Token 值（用于表单隐藏字段）。
     */
    public static function csrfField(): string
    {
        $app = \Finch\App::getInstance();
        $token = $app->session->csrfToken();

        return '<input type="hidden" name="_token" value="' . self::escAttr($token) . '">';
    }

    /**
     * 生成 HTTP Method 覆盖字段（用于 PUT/PATCH/DELETE 表单）。
     */
    public static function methodField(string $method): string
    {
        $method = strtoupper($method);

        return '<input type="hidden" name="_method" value="' . self::escAttr($method) . '">';
    }

    /**
     * 判断字符串是否包含潜在 XSS 攻击向量。
     */
    public static function looksLikeXss(string $value): bool
    {
        $lower = strtolower($value);

        $patterns = [
            '/<script\b/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
            '/<applet\b/i',
            '/<form\b/i',
            '/expression\s*\(/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成随机安全令牌。
     */
    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * 常量时间比较字符串（防时序攻击）。
     */
    public static function constantTimeEquals(string $known, string $input): bool
    {
        return hash_equals($known, $input);
    }

    /**
     * 检测 IP 是否为私网/保留地址（防 SSRF）。
     */
    public static function isPrivateIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true; // 无法解析的 IP 视为私网
        }

        $privateRanges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['0.0.0.0', '0.255.255.255'],
        ];

        foreach ($privateRanges as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($long >= $startLong && $long <= $endLong) {
                return true;
            }
        }

        // IPv6 回环
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip === '::1' || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 脱敏手机号（中间 4 位用 * 替换）。
     */
    public static function maskPhone(string $phone): string
    {
        if (mb_strlen($phone) < 7) {
            return str_repeat('*', mb_strlen($phone));
        }

        return mb_substr($phone, 0, 3) . '****' . mb_substr($phone, -4);
    }

    /**
     * 脱敏邮箱（@ 前保留首尾字符）。
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (mb_strlen($local) <= 2) {
            return str_repeat('*', mb_strlen($local)) . '@' . $domain;
        }

        return mb_substr($local, 0, 1) . str_repeat('*', mb_strlen($local) - 2) . mb_substr($local, -1) . '@' . $domain;
    }

    /**
     * 脱敏 API Key（仅保留前 4 和后 4 位）。
     */
    public static function maskApiKey(string $key): string
    {
        $len = mb_strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return mb_substr($key, 0, 4) . str_repeat('*', $len - 8) . mb_substr($key, -4);
    }
}
