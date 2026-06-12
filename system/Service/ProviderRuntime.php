<?php

/**
 * Finch\Service\ProviderRuntime - Provider 插件运行时桥接
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\App;
use Finch\Core\Logger;
use Throwable;

final class ProviderRuntime
{
    /**
     * 调用 provider 钩子；若无可用结果则返回 null 由服务层 fallback。
     *
     * @param array<string,mixed> $payload
     */
    public static function dispatch(string $domain, string $provider, string $action, array $payload): mixed
    {
        $domainKey = self::normalize($domain);
        $providerKey = self::normalize($provider);
        $actionKey = self::normalize($action);

        if ($domainKey === '' || $providerKey === '' || $actionKey === '') {
            return null;
        }

        try {
            $app = App::getInstance();
            $hooks = $app->hooks;
        } catch (Throwable) {
            return null;
        }

        $logger = self::resolveLogger($app);
        $value = null;

        try {
            $value = $hooks->filter(
                'fp_provider_' . $domainKey . '_' . $providerKey . '_' . $actionKey,
                $value,
                $payload,
            );
        } catch (Throwable $e) {
            $logger?->error('Provider hook failed', [
                'domain' => $domainKey,
                'provider' => $providerKey,
                'action' => $actionKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($value !== null) {
            return $value;
        }

        try {
            return $hooks->filter(
                'fp_provider_' . $domainKey . '_' . $actionKey,
                $value,
                $providerKey,
                $payload,
            );
        } catch (Throwable $e) {
            $logger?->error('Provider fallback hook failed', [
                'domain' => $domainKey,
                'provider' => $providerKey,
                'action' => $actionKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private static function resolveLogger(App $app): ?Logger
    {
        try {
            $logger = $app->logger;
        } catch (Throwable) {
            return null;
        }

        return $logger instanceof Logger ? $logger : null;
    }
}
