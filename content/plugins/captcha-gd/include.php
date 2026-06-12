<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_captcha_captcha_gd_verify', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        $token = trim((string) ($payload['token'] ?? ''));

        return [
            'ok' => $token !== '',
            'enabled' => true,
            'message' => $token !== '' ? 'captcha-gd token accepted' : 'captcha-gd token missing',
        ];
    });
};
