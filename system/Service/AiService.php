<?php

/**
 * Finch\Service\AiService - AI 门面（可降级）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\App;
use Finch\Model\AiLog;
use Finch\Model\AiModel;
use Throwable;

final class AiService
{
    /**
     * @param array<string,mixed> $context
     * @return array{ok:bool,provider:string,model:string,content:string,message:string}
     */
    public function complete(string $prompt, array $context = []): array
    {
        $provider = $this->resolveProvider($context);
        if ($provider !== '') {
            $providerResult = ProviderRuntime::dispatch('ai', $provider, 'complete', [
                'prompt' => trim($prompt),
                'context' => $context,
                'model' => isset($context['model']) && is_scalar($context['model']) ? (string) $context['model'] : '',
            ]);

            if (is_array($providerResult)) {
                $ok = !isset($providerResult['ok']) || (bool) $providerResult['ok'];

                return [
                    'ok' => $ok,
                    'provider' => isset($providerResult['provider']) && is_scalar($providerResult['provider'])
                        ? (string) $providerResult['provider']
                        : $provider,
                    'model' => isset($providerResult['model']) && is_scalar($providerResult['model'])
                        ? (string) $providerResult['model']
                        : (isset($context['model']) && is_scalar($context['model']) ? (string) $context['model'] : ''),
                    'content' => isset($providerResult['content']) && is_scalar($providerResult['content'])
                        ? (string) $providerResult['content']
                        : '',
                    'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                        ? (string) $providerResult['message']
                        : ($ok ? 'ai provider accepted' : 'ai provider rejected'),
                ];
            }
        }

        $model = AiModel::defaultRow();
        if ($model === null) {
            return [
                'ok' => false,
                'provider' => '',
                'model' => '',
                'content' => '',
                'message' => 'no active ai model',
            ];
        }

        $content = '[AI fallback] ' . mb_substr(trim($prompt), 0, 200);

        AiLog::write([
            'model_id' => (int) ($model['id'] ?? 0),
            'model_name' => (string) ($model['model'] ?? ''),
            'platform_name' => (string) ($model['platform'] ?? ''),
            'user_id' => isset($context['user_id']) && is_numeric($context['user_id']) ? (int) $context['user_id'] : null,
            'caller_type' => isset($context['caller_type']) ? (string) $context['caller_type'] : 'service',
            'caller_name' => isset($context['caller_name']) ? (string) $context['caller_name'] : 'AiService',
            'scene' => isset($context['scene']) ? (string) $context['scene'] : 'completion',
            'status' => 'success',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'duration_ms' => 0,
            'error_code' => null,
            'request_hash' => hash('sha256', trim($prompt)),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'provider' => (string) ($model['platform'] ?? ''),
            'model' => (string) ($model['model'] ?? ''),
            'content' => $content,
            'message' => 'ai facade fallback response',
        ];
    }

    /** @param array<string,mixed> $context */
    private function resolveProvider(array $context): string
    {
        if (isset($context['provider']) && is_scalar($context['provider'])) {
            return trim((string) $context['provider']);
        }

        if (isset($context['ai_provider']) && is_scalar($context['ai_provider'])) {
            return trim((string) $context['ai_provider']);
        }

        try {
            return trim((string) App::getInstance()->settings->get('ai_provider', ''));
        } catch (Throwable) {
            return '';
        }
    }
}
