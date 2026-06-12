<?php

/**
 * Finch\Service\CaptchaService - 验证码门面（可降级）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Settings;

final class CaptchaService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /** @return array{ok:bool,provider:string,enabled:bool,message:string} */
    public function verify(string $token, string $context = 'default'): array
    {
        $provider = (string) $this->settings->get('captcha_provider', 'captcha-gd');

        if ($provider !== '') {
            $providerResult = ProviderRuntime::dispatch('captcha', $provider, 'verify', [
                'token' => trim($token),
                'context' => trim($context) !== '' ? $context : 'default',
            ]);

            if (is_array($providerResult)) {
                $ok = !isset($providerResult['ok']) || (bool) $providerResult['ok'];

                return [
                    'ok' => $ok,
                    'provider' => $provider,
                    'enabled' => !isset($providerResult['enabled']) || (bool) $providerResult['enabled'],
                    'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                        ? (string) $providerResult['message']
                        : ($ok ? 'captcha accepted' : 'captcha rejected'),
                ];
            }
        }

        if ($provider === '') {
            return [
                'ok' => true,
                'provider' => 'none',
                'enabled' => false,
                'message' => 'captcha disabled',
            ];
        }

        // Stage19 fallback behavior: deterministic pass-through when no concrete provider runtime is loaded.
        return [
            'ok' => trim($token) !== '',
            'provider' => $provider,
            'enabled' => true,
            'message' => trim($token) !== '' ? 'captcha accepted' : 'captcha token missing',
        ];
    }
}
