<?php

/**
 * Finch PHP - 启动引导
 *
 * 职责（见 PROJECT_PLAN §4.1）：
 *   1. 定义核心路径常量
 *   2. 注册自实现的 PSR-4 自动加载（Finch\ => system/）
 *   3. 设置临时时区（settings 加载前），由 App::init() 后续覆盖为 site_timezone
 *
 * 本文件不连接数据库、不加载运行期设置；这些交由 Finch\App::init()。
 */

declare(strict_types=1);

// --- 核心路径常量 ---
// 本文件位于 system/，故站点根目录是其上一级。
define('FP_SYSTEM_DIR', __DIR__);                  // 系统核心目录
define('FP_PATH', dirname(__DIR__));               // 站点根目录
define('FP_CONTENT_DIR', FP_PATH . '/content');   // 用户内容目录
define('FP_STORAGE_DIR', FP_PATH . '/storage');   // 运行时数据目录
define('FP_CONFIG_FILE', FP_PATH . '/config.php'); // 安装级配置文件
define('FP_START_TIME', microtime(true));          // 启动时间戳（性能统计）

// --- 自实现 PSR-4 自动加载：Finch\ => system/ ---
spl_autoload_register(static function (string $class): void {
    $prefix = 'Finch\\';
    $prefixLength = strlen($prefix);

    if (strncmp($class, $prefix, $prefixLength) !== 0) {
        return;
    }

    // Finch\Core\Router => system/Core/Router.php
    // Finch\App         => system/App.php
    $relative = substr($class, $prefixLength);
    $relativePath = str_replace('\\', '/', $relative) . '.php';
    $file = FP_SYSTEM_DIR . '/' . $relativePath;

    if (is_file($file)) {
        require $file;
    }
});

// --- 临时时区：保证 settings 加载前的 date/time 操作正确 ---
// 安装级时区来自 config.php['app']['timezone']（默认 UTC）。
// 此处仅做最小设置，合法性校验与 site_timezone 覆盖在 App::init() 内完成。
date_default_timezone_set('UTC');
