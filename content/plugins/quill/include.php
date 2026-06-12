<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_editor_quill_resolve', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        return [
            'ok' => true,
            'provider' => 'quill',
            'toolbar' => ['bold', 'italic', 'underline', 'link', 'code-block'],
            'message' => 'quill editor provider ready',
        ];
    });
};
