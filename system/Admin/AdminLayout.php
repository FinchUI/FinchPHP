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
        $editorAssets = $this->resolveEditorAssets();

        return '<!DOCTYPE html><html lang="' . $this->escape($this->app->lang->locale()) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title>'
            . $this->adminStyleLinks($assets['css'])
            . $this->adminStyleLinks($editorAssets['css'])
            . '</head><body class="fp-admin-body">'
            . '<div class="fp-admin-shell">'
            . $this->adminSidebar()
            . '<div class="fp-admin-main">'
            . $this->adminTopbar($title)
            . '<main class="fp-admin-content">' . $body . '</main>'
            . '</div>'
            . '</div>'
            . $this->adminScriptTags($assets['js'])
            . $this->adminScriptTags($editorAssets['js'])
            . '</body></html>';
    }

    private function adminSidebar(): string
    {
        $siteName = (string) $this->app->settings->get('site_name', 'Finch PHP');
        $html = '<aside class="fp-admin-sidebar">'
            . '<div class="fp-admin-brand">'
            . '<div class="fp-admin-brand-mark" aria-hidden="true">FP</div>'
            . '<div class="fp-admin-brand-text">'
            . '<strong>' . $this->escape($this->app->lang->get('admin.topbar.brand')) . '</strong>'
            . '<small>' . $this->escape($siteName) . '</small>'
            . '</div>'
            . '</div>'
            . '<nav class="fp-admin-nav" aria-label="' . $this->escape($this->app->lang->get('admin.topbar.nav_label')) . '">';

        foreach ($this->adminMenuItems() as $item) {
            $active = $this->isMenuActive($item['path']) ? ' active' : '';
            $html .= '<a class="fp-admin-nav-link' . $active . '" href="' . $this->adminUrl($item['path']) . '">'
                . '<span class="fp-admin-nav-icon ' . $this->escape($item['icon']) . '" aria-hidden="true"></span>'
                . '<span class="fp-admin-nav-label">' . $this->escape($this->app->lang->get($item['label'])) . '</span>'
                . '</a>';
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
            . '<span class="fp-admin-welcome"><span class="ti ti-user-circle" aria-hidden="true"></span>' . $this->escape($this->app->lang->get('admin.topbar.welcome')) . $this->escape($this->currentAdminName()) . '</span>'
            . '<a class="fp-admin-top-link" href="' . $this->adminUrl('/admin/settings') . '"><span class="ti ti-settings" aria-hidden="true"></span><span>' . $this->escape($this->app->lang->get('admin.topbar.settings')) . '</span></a>'
            . '<a class="fp-admin-top-link" href="/"><span class="ti ti-world" aria-hidden="true"></span><span>' . $this->escape($this->app->lang->get('admin.topbar.frontend')) . '</span></a>'
            . '<form method="post" action="' . $this->adminUrl('/admin/logout') . '" class="fp-admin-logout-form">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<button type="submit" class="secondary"><span class="ti ti-logout" aria-hidden="true"></span><span>' . $this->escape($this->app->lang->get('admin.topbar.logout')) . '</span></button>'
            . '</form></div></header>';
    }

    /** @return list<array{path: string, label: string, icon: string}> */
    private function adminMenuItems(): array
    {
        return [
            ['path' => '/admin', 'label' => 'admin.menu.dashboard', 'icon' => 'ti ti-layout-dashboard'],
            ['path' => '/admin/posts', 'label' => 'admin.menu.posts', 'icon' => 'ti ti-article'],
            ['path' => '/admin/pages', 'label' => 'admin.menu.pages', 'icon' => 'ti ti-file-text'],
            ['path' => '/admin/categories', 'label' => 'admin.menu.categories', 'icon' => 'ti ti-category'],
            ['path' => '/admin/tags', 'label' => 'admin.menu.tags', 'icon' => 'ti ti-tags'],
            ['path' => '/admin/comments', 'label' => 'admin.menu.comments', 'icon' => 'ti ti-message-circle'],
            ['path' => '/admin/users', 'label' => 'admin.menu.users', 'icon' => 'ti ti-users'],
            ['path' => '/admin/tokens', 'label' => 'admin.menu.tokens', 'icon' => 'ti ti-key'],
            ['path' => '/admin/extensions', 'label' => 'admin.menu.extensions', 'icon' => 'ti ti-puzzle'],
            ['path' => '/admin/modules', 'label' => 'admin.menu.modules', 'icon' => 'ti ti-box'],
            ['path' => '/admin/themes', 'label' => 'admin.menu.themes', 'icon' => 'ti ti-palette'],
            ['path' => '/admin/ai', 'label' => 'admin.menu.ai', 'icon' => 'ti ti-robot'],
            ['path' => '/admin/uploads', 'label' => 'admin.menu.uploads', 'icon' => 'ti ti-photo-up'],
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

        return (string) ($user?->display_name ?: $user?->username ?: $this->app->lang->get('admin.topbar.admin'));
    }

    /** @param list<string> $css */
    private function adminStyleLinks(array $css): string
    {
        $links = '';
        foreach ($css as $href) {
            $links .= '<link rel="stylesheet" href="' . $this->escape($this->assetUrlWithVersion($href)) . '">';
        }

        return $links;
    }

    /** @param list<string> $js */
    private function adminScriptTags(array $js): string
    {
        $tags = '';
        foreach ($js as $src) {
            $tags .= '<script defer src="' . $this->escape($this->assetUrlWithVersion($src)) . '"></script>';
        }

        return $tags;
    }

    /** @return array{css: list<string>, js: list<string>} */
    private function resolveAdminStyleAssets(): array
    {
        $fallbackCss = [
            '/content/plugins/tabler/assets/css/tabler.min.css',
            '/content/plugins/tabler/assets/css/tabler-icons.min.css',
            '/content/plugins/tabler/assets/css/admin.css',
        ];
        $fallbackJs = [
            '/content/plugins/tabler/assets/js/tabler.min.js',
            '/content/plugins/tabler/assets/js/admin.js',
        ];

        if (isset($this->app->asset)) {
            $fallbackCss = [
                $this->app->asset->pluginAsset('tabler', 'css/tabler.min.css'),
                $this->app->asset->pluginAsset('tabler', 'css/tabler-icons.min.css'),
                $this->app->asset->pluginAsset('tabler', 'css/admin.css'),
            ];
            $fallbackJs = [
                $this->app->asset->pluginAsset('tabler', 'js/tabler.min.js'),
                $this->app->asset->pluginAsset('tabler', 'js/admin.js'),
            ];
        }

        $css = $fallbackCss;
        $js = $fallbackJs;

        $provider = $this->app->hooks->filter('fp_provider_admin_style_tabler_resolve', null, [
            'request' => $this->request,
            'user' => $this->app->user,
        ]);

        if (is_array($provider)) {
            $providedCss = $this->normalizeAssetList($provider['css'] ?? []);
            $providedJs = $this->normalizeAssetList($provider['js'] ?? []);

            if ($providedCss !== []) {
                $css = $providedCss;
            }

            if ($providedJs !== []) {
                $js = $providedJs;
            }
        }

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

    private function assetUrlWithVersion(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $path = $parts['path'] ?? null;
        if (!is_string($path) || $path === '' || !str_starts_with($path, '/')) {
            return $url;
        }

        $file = FP_PATH . $path;
        if (!is_file($file)) {
            return $url;
        }

        $query = [];
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        $query['_v'] = (string) filemtime($file);
        $parts['query'] = http_build_query($query);

        return $this->buildUrlFromParts($parts);
    }

    /** @param array<string, mixed> $parts */
    private function buildUrlFromParts(array $parts): string
    {
        $url = '';
        if (isset($parts['scheme']) && is_string($parts['scheme']) && $parts['scheme'] !== '') {
            $url .= $parts['scheme'] . '://';
        }

        if (isset($parts['user']) && is_string($parts['user']) && $parts['user'] !== '') {
            $url .= $parts['user'];
            if (isset($parts['pass']) && is_string($parts['pass']) && $parts['pass'] !== '') {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        if (isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '') {
            $url .= $parts['host'];
        }

        if (isset($parts['port']) && is_int($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= (string) ($parts['path'] ?? '');

        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /** @param array<string, list<string>> $errors */
    private function fieldError(string $name, array $errors): string
    {
        if (!isset($errors[$name][0])) {
            return '';
        }

        return '<div class="fp-error-text">' . $this->escape($errors[$name][0]) . '</div>';
    }
}
