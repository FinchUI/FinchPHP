<?php

/**
 * Finch\Middleware\CsrfMiddleware - 后台表单 CSRF 校验
 */

declare(strict_types=1);

namespace Finch\Middleware;

use Finch\App;
use Finch\Core\Request;
use Finch\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $route = [], array $args = []): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->header('X-XSRF-TOKEN');
        if (!is_string($token) || $token === '') {
            $token = $request->post('_token');
        }

        if (App::getInstance()->session->verifyCsrfToken(is_string($token) ? $token : null)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return (new Response())->json([
                'code'    => 1003,
                'message' => 'CSRF 校验失败',
                'errors'  => ['_token' => ['CSRF token 无效或已过期。']],
            ], 419);
        }

        return (new Response())->html($this->csrfPage(), 419);
    }

    private function csrfPage(): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>表单已过期 - Finch PHP</title></head>'
            . '<body style="font-family:sans-serif;padding:3rem">'
            . '<h1>表单已过期</h1><p>请返回上一页刷新后重试。</p></body></html>';
    }
}
