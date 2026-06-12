<?php

/**
 * Finch\Service\MailService - 邮件门面（可降级）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Database;
use Finch\Core\Settings;

final class MailService
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @param array<string,mixed> $options
     * @return array{ok:bool,provider:string,transport:string,message:string}
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false,
                'provider' => (string) $this->settings->get('mail_provider', 'notify-email'),
                'transport' => 'none',
                'message' => '收件人邮箱无效',
            ];
        }

        $provider = (string) $this->settings->get('mail_provider', 'notify-email');
        $smtpHost = trim((string) $this->settings->get('smtp_host', ''));
        $transport = $smtpHost === '' ? 'php-mail' : 'smtp';

        $providerResult = ProviderRuntime::dispatch('mail', $provider, 'send', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'options' => $options,
            'transport' => $transport,
        ]);

        if (is_array($providerResult)) {
            $ok = !isset($providerResult['ok']) || (bool) $providerResult['ok'];

            return [
                'ok' => $ok,
                'provider' => $provider,
                'transport' => isset($providerResult['transport']) && is_scalar($providerResult['transport'])
                    ? (string) $providerResult['transport']
                    : $transport,
                'message' => isset($providerResult['message']) && is_scalar($providerResult['message'])
                    ? (string) $providerResult['message']
                    : ($ok ? 'mail provider accepted' : 'mail provider rejected'),
            ];
        }

        // Stage19 facade behavior: deterministic fallback without hard-failing when provider is absent.
        return [
            'ok' => true,
            'provider' => $provider,
            'transport' => $transport,
            'message' => 'mail facade accepted',
        ];
    }
}
