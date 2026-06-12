<?php

/**
 * Finch\Core\Middleware - 中间件管道
 *
 * 路由可声明 middleware => ['cors', 'auth', 'role:edit_post']。
 * throttle 参数在 1.0 解析保存但不执行，执行逻辑留给 1.1。
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\Middleware\AuthMiddleware;
use Finch\Middleware\ApiAbilityMiddleware;
use Finch\Middleware\ApiAuthMiddleware;
use Finch\Middleware\CorsMiddleware;
use Finch\Middleware\CsrfMiddleware;
use Finch\Middleware\MiddlewareInterface;
use Finch\Middleware\RoleMiddleware;
use InvalidArgumentException;

final class Middleware
{
    /** @var array<string, callable():MiddlewareInterface> */
    private array $registry = [];

    public function __construct()
    {
        $this->register('cors', static fn (): MiddlewareInterface => new CorsMiddleware());
        $this->register('csrf', static fn (): MiddlewareInterface => new CsrfMiddleware());
        $this->register('auth', static fn (): MiddlewareInterface => new AuthMiddleware());
        $this->register('api_auth', static fn (): MiddlewareInterface => new ApiAuthMiddleware());
        $this->register('ability', static fn (): MiddlewareInterface => new ApiAbilityMiddleware());
        $this->register('role', static fn (): MiddlewareInterface => new RoleMiddleware());
    }

    /** 注册命名中间件。 */
    public function register(string $name, callable $factory): void
    {
        $this->registry[$name] = $factory;
    }

    /**
     * 执行中间件链。
     *
     * @param list<string>|string $middleware
     * @param array<string, mixed> $route
     */
    public function handle(Request $request, array|string $middleware, callable $destination, array $route = []): Response
    {
        $stack = is_array($middleware) ? array_values($middleware) : [$middleware];
        $next = static function (Request $request) use ($destination): Response {
            $result = $destination($request);

            return $result instanceof Response ? $result : (new Response())->html((string) $result);
        };

        foreach (array_reverse($stack) as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            [$name, $args] = $this->parseEntry($entry);
            $middlewareInstance = $this->resolve($name);
            $currentNext = $next;
            $next = static fn (Request $request): Response => $middlewareInstance->handle($request, $currentNext, $route, $args);
        }

        return $next($request);
    }

    /** @return array{0:string,1:list<string>} */
    private function parseEntry(string $entry): array
    {
        [$name, $rawArgs] = array_pad(explode(':', $entry, 2), 2, '');
        $args = $rawArgs === '' ? [] : array_values(array_filter(explode(',', $rawArgs), static fn (string $arg): bool => $arg !== ''));

        return [$name, $args];
    }

    private function resolve(string $name): MiddlewareInterface
    {
        if (!isset($this->registry[$name])) {
            throw new InvalidArgumentException("未注册的中间件：{$name}");
        }

        $middleware = ($this->registry[$name])();
        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException("中间件 {$name} 必须实现 MiddlewareInterface。");
        }

        return $middleware;
    }
}
