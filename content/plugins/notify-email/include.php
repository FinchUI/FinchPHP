<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_mail_notify_email_send', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        $to = trim((string) ($payload['to'] ?? ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false,
                'transport' => 'none',
                'message' => 'notify-email invalid recipient',
            ];
        }

        return [
            'ok' => true,
            'transport' => isset($payload['transport']) && is_scalar($payload['transport'])
                ? (string) $payload['transport']
                : 'php-mail',
            'message' => 'notify-email provider accepted',
        ];
    });
};
