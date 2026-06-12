<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_ai_ai_openai_complete', static function (mixed $value, array $payload = []): mixed {
        if ($value !== null) {
            return $value;
        }

        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $model = trim((string) ($payload['model'] ?? ''));
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        return [
            'ok' => true,
            'provider' => 'ai-openai',
            'model' => $model,
            'content' => '[ai-openai mock] ' . mb_substr($prompt, 0, 200),
            'message' => 'ai-openai provider accepted',
        ];
    });
};
