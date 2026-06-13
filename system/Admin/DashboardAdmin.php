<?php

/**
 * Finch\Admin\DashboardAdmin - 后台仪表盘占位页
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;

final class DashboardAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $siteName = $this->app->settings->get('site_name', 'Finch');
        $user = $this->app->user;
        $u = fn (string $path): string => $this->adminUrl($path);
        $shortcuts = [
            ['path' => '/admin/posts', 'label' => 'admin.menu.posts', 'icon' => 'ti ti-article'],
            ['path' => '/admin/pages', 'label' => 'admin.menu.pages', 'icon' => 'ti ti-file-text'],
            ['path' => '/admin/categories', 'label' => 'admin.menu.categories', 'icon' => 'ti ti-category'],
            ['path' => '/admin/tags', 'label' => 'admin.menu.tags', 'icon' => 'ti ti-tags'],
            ['path' => '/admin/comments', 'label' => 'admin.menu.comments', 'icon' => 'ti ti-message-circle'],
            ['path' => '/admin/users', 'label' => 'admin.menu.users', 'icon' => 'ti ti-users'],
            ['path' => '/admin/tokens', 'label' => 'admin.menu.tokens', 'icon' => 'ti ti-key'],
            ['path' => '/admin/extensions', 'label' => 'admin.menu.extensions', 'icon' => 'ti ti-puzzle'],
            ['path' => '/admin/uploads', 'label' => 'admin.menu.uploads', 'icon' => 'ti ti-photo-up'],
            ['path' => '/admin/settings', 'label' => 'admin.dashboard.system_settings', 'icon' => 'ti ti-settings'],
        ];

        $shortcutHtml = '';
        foreach ($shortcuts as $item) {
            $shortcutHtml .= '<a class="fp-shortcut" href="' . $u($item['path']) . '">'
                . '<span class="fp-shortcut-icon ' . $this->escape($item['icon']) . '" aria-hidden="true"></span>'
                . '<span>' . $this->escape($lang->get($item['label'])) . '</span>'
                . '</a>';
        }

        $body = '<section class="panel">'
            . '<h2>' . $this->escape($lang->get('admin.dashboard.site_overview')) . '</h2>'
            . '<div class="fp-dashboard-grid">'
            . '<article class="fp-stat-card"><div class="fp-stat-label">' . $this->escape($lang->get('admin.dashboard.site_name')) . '</div><div class="fp-stat-value">' . $this->escape((string) $siteName) . '</div></article>'
            . '<article class="fp-stat-card"><div class="fp-stat-label">' . $this->escape($lang->get('admin.dashboard.current_login')) . '</div><div class="fp-stat-value">' . $this->escape((string) ($user?->display_name ?: $user?->username ?: '')) . '</div></article>'
            . '<article class="fp-stat-card"><div class="fp-stat-label">' . $this->escape($lang->get('admin.dashboard.timezone')) . '</div><div class="fp-stat-value">' . $this->escape((string) $this->app->settings->get('site_timezone', 'UTC')) . '</div></article>'
            . '</div>'
            . '</section>'
            . '<section class="panel">'
            . '<h2>' . $this->escape($lang->get('admin.dashboard.shortcuts')) . '</h2>'
            . '<div class="fp-shortcuts">'
            . $shortcutHtml
            . '</div>'
            . '</section>';

        // 开发模式插件钩子：在快捷方式之后注入面板
        $devPanel = $this->app->hooks->filter('fp_dashboard_after_shortcuts', '', []);
        if (is_string($devPanel) && $devPanel !== '') {
            $body .= $devPanel;
        }

        return $this->html($this->adminShell($lang->get('admin.dashboard.title'), $body));
    }
}
