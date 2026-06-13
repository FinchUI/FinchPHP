<?php

declare(strict_types=1);

/**
 * Quill 编辑器提供者插件
 *
 * 向文章/页面编辑界面注入 Quill 富文本编辑器。
 * 通过 fp_provider_editor_quill_resolve 钩子注册为编辑器提供者。
 * 输出 HTML 经 HtmlCleaner 清洗后入库。
 */

return static function (\Finch\App $app, array $meta = []): void {
    $asset = static function (string $path) use ($app): string {
        if (isset($app->asset)) {
            return $app->asset->pluginAsset('quill', ltrim($path, '/'));
        }

        return '/content/plugins/quill/assets/' . ltrim($path, '/');
    };

    $app->hooks->add('fp_provider_editor_quill_resolve', static function (mixed $value, array $payload = []) use ($asset): mixed {
        if ($value !== null) {
            return $value;
        }

        return [
            'ok'        => true,
            'provider'  => 'quill',
            'css'       => [
                $asset('css/quill.snow.css'),
            ],
            'js'        => [
                $asset('js/quill.min.js'),
            ],
            'message'   => 'quill editor provider ready',
        ];
    });
};
