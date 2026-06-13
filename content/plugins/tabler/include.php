<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $asset = static function (string $path) use ($app): string {
        if (isset($app->asset)) {
            return $app->asset->pluginAsset('tabler', ltrim($path, '/'));
        }

        return '/content/plugins/tabler/assets/' . ltrim($path, '/');
    };

    $app->hooks->add('fp_provider_admin_style_tabler_resolve', static function (mixed $value, array $payload = []) use ($asset): mixed {
        if ($value !== null) {
            return $value;
        }

        return [
            'ok' => true,
            'provider' => 'tabler',
            'css' => [
                $asset('css/tabler.min.css'),
                $asset('css/tabler-icons.min.css'),
                $asset('css/admin.css'),
            ],
            'js' => [
                $asset('js/tabler.min.js'),
                $asset('js/admin.js'),
            ],
            'message' => 'tabler style provider ready',
        ];
    });
};
