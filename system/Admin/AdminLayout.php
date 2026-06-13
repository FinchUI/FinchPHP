<?php

/**
 * Finch\Admin\AdminLayout - 后台简易布局助手
 */

declare(strict_types=1);

namespace Finch\Admin;

trait AdminLayout
{
    private function adminShell(string $title, string $body): string
    {
        $body = $this->normalizeAdminLinks($body);
        $assets = $this->resolveAdminStyleAssets();

        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title>'
            . $this->adminStyleLinks($assets['css'])
            . '</head><body class="fp-admin-body">'
            . '<div class="fp-admin-shell">'
            . $this->adminSidebar()
            . '<div class="fp-admin-main">'
            . $this->adminTopbar($title)
            . '<main class="fp-admin-content">' . $body . '</main>'
            . '</div>'
            . '</div>'
            . $this->adminScriptTags($assets['js'])
            . '</body></html>';
    }

    private function adminSidebar(): string
    {
        $siteName = (string) $this->app->settings->get('site_name', 'Finch PHP');
        $html = '<aside class="fp-admin-sidebar">'
            . '<div class="fp-admin-brand">'
            . '<div class="fp-admin-brand-mark" aria-hidden="true">FP</div>'
            . '<div class="fp-admin-brand-text">'
            . '<strong>Finch Admin</strong>'
            . '<small>' . $this->escape($siteName) . '</small>'
            . '</div>'
            . '</div>'
            . '<nav class="fp-admin-nav" aria-label="后台主菜单">';

        foreach ($this->adminMenuItems() as $item) {
            $active = $this->isMenuActive($item['path']) ? ' active' : '';
            $html .= '<a class="fp-admin-nav-link' . $active . '" href="' . $this->adminUrl($item['path']) . '">' . $this->escape($item['label']) . '</a>';
        }

        return $html . '</nav></aside>';
    }

    private function adminTopbar(string $title): string
    {
        return '<header class="fp-admin-topbar">'
            . '<div class="fp-admin-topbar-title">'
            . '<h1>' . $this->escape($title) . '</h1>'
            . '</div>'
            . '<div class="fp-admin-topbar-actions">'
            . '<span class="fp-admin-welcome">欢迎，' . $this->escape($this->currentAdminName()) . '</span>'
            . '<a class="fp-admin-top-link" href="' . $this->adminUrl('/admin/settings') . '">设置</a>'
            . '<a class="fp-admin-top-link" href="/">前台</a>'
            . '<form method="post" action="' . $this->adminUrl('/admin/logout') . '" class="fp-admin-logout-form">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<button type="submit" class="secondary">退出登录</button>'
            . '</form></div></header>';
    }

    /** @return list<array{path: string, label: string}> */
    private function adminMenuItems(): array
    {
        return [
            ['path' => '/admin', 'label' => '仪表盘'],
            ['path' => '/admin/posts', 'label' => '文章'],
            ['path' => '/admin/pages', 'label' => '页面'],
            ['path' => '/admin/categories', 'label' => '分类'],
            ['path' => '/admin/tags', 'label' => '标签'],
            ['path' => '/admin/comments', 'label' => '评论'],
            ['path' => '/admin/users', 'label' => '用户'],
            ['path' => '/admin/tokens', 'label' => 'Token'],
            ['path' => '/admin/extensions', 'label' => '扩展'],
            ['path' => '/admin/uploads', 'label' => '媒体库'],
        ];
    }

    private function isMenuActive(string $path): bool
    {
        $current = trim((string) $this->request->query('fp', ''), '/');
        if ($current === '') {
            $current = trim($this->request->path(), '/');
        }

        $target = trim($path, '/');
        if ($target === 'admin') {
            return $current === 'admin' || $current === 'admin/dashboard';
        }

        return $current === $target || str_starts_with($current, $target . '/');
    }

    private function currentAdminName(): string
    {
        $user = $this->app->user;

        return (string) ($user?->display_name ?: $user?->username ?: '管理员');
    }

    /** @param list<string> $css */
    private function adminStyleLinks(array $css): string
    {
        $links = '';
        foreach ($css as $href) {
            $links .= '<link rel="stylesheet" href="' . $this->escape($href) . '">';
        }

        return $links;
    }

    /** @param list<string> $js */
    private function adminScriptTags(array $js): string
    {
        $tags = '';
        foreach ($js as $src) {
            $tags .= '<script defer src="' . $this->escape($src) . '"></script>';
        }

        return $tags;
    }

    /** @return array{css: list<string>, js: list<string>} */
    private function resolveAdminStyleAssets(): array
    {
        $baseCss = '/system/assets/css/admin.css';
        if (isset($this->app->asset)) {
            $baseCss = $this->app->asset->systemAsset('css/admin.css');
        }

        $css = [];
        $js = [];

        $provider = $this->app->hooks->filter('fp_provider_admin_style_tabler_resolve', null, [
            'request' => $this->request,
            'user' => $this->app->user,
        ]);

        if (is_array($provider)) {
            $css = $this->normalizeAssetList($provider['css'] ?? []);
            $js = $this->normalizeAssetList($provider['js'] ?? []);
        }

        $css[] = $baseCss;

        return [
            'css' => array_values(array_unique($css)),
            'js' => array_values(array_unique($js)),
        ];
    }

    /** @return list<string> */
    private function normalizeAssetList(mixed $assets): array
    {
        if (!is_array($assets)) {
            return [];
        }

        $list = [];
        foreach ($assets as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }

            $list[] = $trimmed;
        }

        return $list;
    }

    private function normalizeAdminLinks(string $html): string
    {
        $normalized = preg_replace_callback(
            '/\b(href|action)\s*=\s*(["\'])(\/admin[^"\']*)\2/i',
            function (array $matches): string {
                $attr = $matches[1];
                $quote = $matches[2];
                $url = $this->toDynamicAdminUrl($matches[3]);

                return $attr . '=' . $quote . $this->escape($url) . $quote;
            },
            $html,
        );

        return is_string($normalized) ? $normalized : $html;
    }

    private function toDynamicAdminUrl(string $url): string
    {
        if (!str_starts_with($url, '/admin')) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : $url;
        $dynamic = $this->adminUrl($path);

        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $dynamic .= '&' . $parts['query'];
        }

        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $dynamic .= '#' . $parts['fragment'];
        }

        return $dynamic;
    }

    /** @param array<string, list<string>> $errors */
    private function fieldError(string $name, array $errors): string
    {
        if (!isset($errors[$name][0])) {
            return '';
        }

        return '<div style="color:#b42318;margin-top:4px">' . $this->escape($errors[$name][0]) . '</div>';
    }
}
