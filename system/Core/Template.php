<?php

/**
 * Finch\Core\Template - 模板加载器
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\App;
use RuntimeException;
use Throwable;

final class Template
{
    public function __construct(
        private readonly Settings $settings,
        private readonly TemplateContext $context,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        $file = $this->resolve($name);
        if ($file === null) {
            throw new RuntimeException('模板不存在：' . $name);
        }

        $pushCount = $this->pushContext($data);
        $sharedSnapshot = $this->applyShared($data);
        $previousView = $this->context->get('view');
        $this->context->set('view', $name);

        try {
            ob_start();
            $app = App::getInstance();
            $template = $this;
            extract($data, EXTR_SKIP);
            require $file;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $this->logger->error('模板渲染失败：' . $e->getMessage());
            throw $e;
        } finally {
            if ($previousView === null) {
                $this->context->remove('view');
            } else {
                $this->context->set('view', $previousView);
            }
            $this->restoreShared($sharedSnapshot);

            for ($i = 0; $i < $pushCount; $i++) {
                $this->context->pop();
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function display(string $name, array $data = []): void
    {
        echo $this->render($name, $data);
    }

    public function path(string $name): ?string
    {
        return $this->resolve($name);
    }

    private function resolve(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $name = str_ends_with($name, '.php') ? $name : $name . '.php';
        $activeTheme = (string) $this->settings->get('active_theme', 'default');

        $candidates = [
            FP_CONTENT_DIR . '/themes/' . $activeTheme . '/template/' . $name,
            FP_SYSTEM_DIR . '/Theme/' . $name,
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function pushContext(array $data): int
    {
        $pushCount = 0;

        foreach (['post', 'comment', 'category', 'tag', 'query'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $this->context->push($data[$key]);
                $pushCount++;
            }
        }

        return $pushCount;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{exists:bool, value:mixed}>
     */
    private function applyShared(array $data): array
    {
        $snapshot = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $snapshot[$key] = [
                'exists' => $this->context->has($key),
                'value'  => $this->context->get($key),
            ];
            $this->context->set($key, $value);
        }

        return $snapshot;
    }

    /**
     * @param array<string, array{exists:bool, value:mixed}> $snapshot
     */
    private function restoreShared(array $snapshot): void
    {
        foreach ($snapshot as $key => $state) {
            if ($state['exists']) {
                $this->context->set($key, $state['value']);
            } else {
                $this->context->remove($key);
            }
        }
    }
}
