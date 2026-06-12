<?php

/**
 * Finch\Service\SmsService - 短信门面（验证码流程）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Database;
use Finch\Core\Settings;
use Finch\Model\SmsCode;

final class SmsService
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return array{ok:bool,provider:string,message:string,expires_in:int,code:string}
     */
    public function issueCode(string $phone, string $scene = 'login', ?string $ip = null): array
    {
        $phone = trim($phone);
        if (!preg_match('/^[0-9+\- ]{6,20}$/', $phone)) {
            return [
                'ok' => false,
                'provider' => (string) $this->settings->get('sms_provider', 'notify-sms'),
                'message' => '手机号格式不正确',
                'expires_in' => 0,
                'code' => '',
            ];
        }

        $provider = (string) $this->settings->get('sms_provider', 'notify-sms');
        $code = (string) random_int(100000, 999999);
        $expiresIn = 300;

        $providerResult = ProviderRuntime::dispatch('sms', $provider, 'issue', [
            'phone' => $phone,
            'scene' => trim($scene) !== '' ? $scene : 'login',
            'ip' => $ip,
            'code' => $code,
            'expires_in' => $expiresIn,
        ]);

        if (is_array($providerResult)) {
            if (isset($providerResult['ok']) && (bool) $providerResult['ok'] === false) {
                return [
                    'ok' => false,
                    'provider' => $provider,
                    'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                        ? (string) $providerResult['message']
                        : 'sms provider rejected',
                    'expires_in' => 0,
                    'code' => '',
                ];
            }

            if (isset($providerResult['code']) && is_scalar($providerResult['code'])) {
                $code = trim((string) $providerResult['code']);
            }

            if (isset($providerResult['expires_in']) && is_numeric($providerResult['expires_in'])) {
                $expiresIn = max(1, (int) $providerResult['expires_in']);
            }
        }

        if ($code === '') {
            return [
                'ok' => false,
                'provider' => $provider,
                'message' => '验证码生成失败',
                'expires_in' => 0,
                'code' => '',
            ];
        }

        SmsCode::issue([
            'phone' => $phone,
            'code' => $code,
            'scene' => trim($scene) !== '' ? $scene : 'login',
            'used' => 0,
            'ip' => $ip,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $expiresIn),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'provider' => $provider,
            'message' => is_array($providerResult) && isset($providerResult['message']) && is_scalar($providerResult['message'])
                ? (string) $providerResult['message']
                : 'sms facade accepted',
            'expires_in' => $expiresIn,
            'code' => $code,
        ];
    }

    /** @return array{ok:bool,message:string} */
    public function verifyCode(string $phone, string $code, string $scene = 'login'): array
    {
        $provider = (string) $this->settings->get('sms_provider', 'notify-sms');
        $providerResult = ProviderRuntime::dispatch('sms', $provider, 'verify', [
            'phone' => trim($phone),
            'code' => trim($code),
            'scene' => trim($scene) !== '' ? $scene : 'login',
        ]);

        if (is_array($providerResult) && array_key_exists('ok', $providerResult)) {
            $ok = (bool) $providerResult['ok'];

            return [
                'ok' => $ok,
                'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                    ? (string) $providerResult['message']
                    : ($ok ? '验证通过' : '验证失败'),
            ];
        }

        $row = SmsCode::latestValid(trim($phone), trim($scene) !== '' ? $scene : 'login');
        if ($row === null) {
            return ['ok' => false, 'message' => '验证码不存在'];
        }

        if ((string) ($row['code'] ?? '') !== trim($code)) {
            return ['ok' => false, 'message' => '验证码错误'];
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ($expiresAt === '' || strtotime($expiresAt . ' UTC') <= time()) {
            return ['ok' => false, 'message' => '验证码已过期'];
        }

        $this->db->table('sms_code')
            ->where('id', (int) $row['id'])
            ->update(['used' => 1]);

        return ['ok' => true, 'message' => '验证通过'];
    }
}
