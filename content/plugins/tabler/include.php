<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_admin_style_tabler_resolve', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        return [
            'ok' => true,
            'provider' => 'tabler',
            'css' => [],
            'js' => [],
            'message' => 'tabler style provider ready',
        ];
    });
};
