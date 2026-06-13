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
            'css' => [
                'https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css',
                'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/tabler-icons.min.css',
            ],
            'js' => [
                'https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js',
            ],
            'message' => 'tabler style provider ready',
        ];
    });
};
