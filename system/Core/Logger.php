<?php

/**
 * Finch\Core\Logger - 日志服务
 *
 * 统一写盘、按用途与日期分流（见 PROJECT_PLAN §4.19.1）。
 * 业务代码不得直接写日志文件，一律经此服务。
 *
 * 日志通道：error / security / api / install / ai
 * 文件命名：storage/logs/{channel}-{Y-m-d}.log
 */

declare(strict_types=1);

namespace Finch\Core;

final class Logger
{
    /** 允许的日志通道 */
    private const CHANNELS = ['error', 'security', 'api', 'install', 'ai'];

    public function __construct(private readonly string $logDir)
    {
    }

    /** 写入 error 通道 */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', 'ERROR', $message, $context);
    }

    /** 写入 error 通道（warning 级别） */
    public function warning(string $message, array $context = []): void
    {
        $this->write('error', 'WARNING', $message, $context);
    }

    /** 写入 security 通道 */
    public function security(string $message, array $context = []): void
    {
        $this->write('security', 'SECURITY', $message, $context);
    }

    /** 写入 api 通道 */
    public function api(string $message, array $context = []): void
    {
        $this->write('api', 'API', $message, $context);
    }

    /** 写入 install 通道 */
    public function install(string $message, array $context = []): void
    {
        $this->write('install', 'INSTALL', $message, $context);
    }

    /** 写入 ai 通道（调用方需自行脱敏 context） */
    public function ai(string $message, array $context = []): void
    {
        $this->write('ai', 'AI', $message, $context);
    }

    /**
     * 实际写盘。写入失败时静默忽略，避免日志故障影响主流程。
     */
    private function write(string $channel, string $level, string $message, array $context): void
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'error';
        }

        if (!is_dir($this->logDir) && !@mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
            return;
        }

        $file = $this->logDir . '/' . $channel . '-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf('[%s] %s: %s', $timestamp, $level, $message);

        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $line .= ' ' . $encoded;
            }
        }

        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
