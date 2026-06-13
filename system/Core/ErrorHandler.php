<?php

/**
 * Finch\Core\ErrorHandler - 错误与异常处理
 *
 * 捕获 PHP 错误/异常（见 PROJECT_PLAN §4.19.1）：
 *   - 开发模式：显示错误详情与堆栈
 *   - 生产模式：不暴露路径/SQL/密钥/堆栈，显示友好错误页
 *
 * 仅负责捕获与呈现；写日志委托给 Logger。
 */

declare(strict_types=1);

namespace Finch\Core;

use Throwable;

final class ErrorHandler
{
    private bool $debug;

    public function __construct(
        private readonly Logger $logger,
        bool $debug = false,
    ) {
        $this->debug = $debug;
    }

    /** 运行时切换调试模式（供 App 加载数据库设置后调用） */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
        ini_set('display_errors', $debug ? '1' : '0');
    }

    /** 注册为全局错误/异常/致命错误处理器 */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    /** 将 PHP 错误转换为异常处理逻辑（屏蔽被 @ 抑制的错误） */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->logger->error($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);

        // 交由 PHP 默认流程决定是否中断（开发模式下显示）
        return !$this->debug;
    }

    /** 处理未捕获异常 */
    public function handleException(Throwable $e): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $this->renderPage($e);
    }

    /** 捕获致命错误（解析/内存等 register_shutdown 才可见的错误） */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'], $fatal, true)) {
            return;
        }

        $this->logger->error($error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type'],
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $this->renderFatal($error['message'], $error['file'], (int) $error['line']);
    }

    /** 渲染异常页面 */
    private function renderPage(Throwable $e): string
    {
        if (!$this->debug) {
            return $this->friendlyPage();
        }

        return sprintf(
            '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>错误</title></head><body style="font-family:monospace;padding:2rem">'
            . '<h1>%s</h1><p>%s</p><p><strong>%s:%d</strong></p><pre>%s</pre></body></html>',
            htmlspecialchars($e::class, ENT_QUOTES),
            htmlspecialchars($e->getMessage(), ENT_QUOTES),
            htmlspecialchars($e->getFile(), ENT_QUOTES),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString(), ENT_QUOTES),
        );
    }

    /** 渲染致命错误页面 */
    private function renderFatal(string $message, string $file, int $line): string
    {
        if (!$this->debug) {
            return $this->friendlyPage();
        }

        return sprintf(
            '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>致命错误</title></head><body style="font-family:monospace;padding:2rem">'
            . '<h1>致命错误</h1><p>%s</p><p><strong>%s:%d</strong></p></body></html>',
            htmlspecialchars($message, ENT_QUOTES),
            htmlspecialchars($file, ENT_QUOTES),
            $line,
        );
    }

    /** 生产模式友好错误页（不暴露任何技术细节） */
    private function friendlyPage(): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>服务暂时不可用</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:4rem">'
            . '<h1>服务暂时不可用</h1>'
            . '<p>系统遇到一个错误，请稍后再试。</p></body></html>';
    }
}
