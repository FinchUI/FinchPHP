<?php

/**
 * Finch PHP - PHP 内置开发服务器路由脚本
 *
 * 用法: php -S localhost:8000 router.php
 *
 * 所有非静态文件请求均经 index.php 路由分发。
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 静态文件直接返回（如果存在）
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // 安全：禁止访问系统目录和配置文件
    $realPath = realpath(__DIR__ . $uri);
    $forbidden = ['/system/', '/storage/', '/config.php', '/.env'];
    foreach ($forbidden as $prefix) {
        if (str_starts_with($realPath, __DIR__ . $prefix)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    return false; // 让 PHP 内置服务器处理静态文件
}

// 其他所有请求经 index.php 路由
require __DIR__ . '/index.php';
