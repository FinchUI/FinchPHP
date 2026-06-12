<?php

/**
 * Finch PHP - 唯一入口
 *
 * 所有前台/后台/API 请求均经此文件路由分发。
 * 该文件只负责引导，不包含业务逻辑。
 */

declare(strict_types=1);

require __DIR__ . '/system/bootstrap.php';

$fp = Finch\App::getInstance();
$fp->init();
$fp->run();
