<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_sms_notify_sms_issue', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if (!preg_match('/^[0-9+\- ]{6,20}$/', $phone)) {
            return [
                'ok' => false,
                'message' => 'notify-sms invalid phone',
            ];
        }

        return [
            'ok' => true,
            'code' => isset($payload['code']) && is_scalar($payload['code'])
                ? (string) $payload['code']
                : (string) random_int(100000, 999999),
            'expires_in' => isset($payload['expires_in']) && is_numeric($payload['expires_in'])
                ? (int) $payload['expires_in']
                : 300,
            'message' => 'notify-sms provider accepted',
        ];
    });

    $app->hooks->add('fp_provider_sms_notify_sms_verify', static function (mixed $value, array $payload = []): mixed {
        return $value;
    });
};
