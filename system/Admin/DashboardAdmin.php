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
        $siteName = $this->app->settings->get('site_name', 'Finch');
        $user = $this->app->user;
        $u = fn (string $path): string => $this->adminUrl($path);
        $shortcuts = [
            ['path' => '/admin/posts', 'label' => '文章', 'icon' => 'ti ti-article'],
            ['path' => '/admin/pages', 'label' => '页面', 'icon' => 'ti ti-file-text'],
            ['path' => '/admin/categories', 'label' => '分类', 'icon' => 'ti ti-category'],
            ['path' => '/admin/tags', 'label' => '标签', 'icon' => 'ti ti-tags'],
            ['path' => '/admin/comments', 'label' => '评论', 'icon' => 'ti ti-message-circle'],
            ['path' => '/admin/users', 'label' => '用户', 'icon' => 'ti ti-users'],
            ['path' => '/admin/tokens', 'label' => 'Token', 'icon' => 'ti ti-key'],
            ['path' => '/admin/extensions', 'label' => '扩展', 'icon' => 'ti ti-puzzle'],
            ['path' => '/admin/uploads', 'label' => '媒体库', 'icon' => 'ti ti-photo-up'],
            ['path' => '/admin/settings', 'label' => '系统设置', 'icon' => 'ti ti-settings'],
        ];

        $shortcutHtml = '';
        foreach ($shortcuts as $item) {
            $shortcutHtml .= '<a class="fp-shortcut" href="' . $u($item['path']) . '">' 
                . '<span class="fp-shortcut-icon ' . $this->escape($item['icon']) . '" aria-hidden="true"></span>'
                . '<span>' . $this->escape($item['label']) . '</span>'
                . '</a>';
        }

        $body = '<section class="panel">'
            . '<h2>站点概览</h2>'
            . '<div class="fp-dashboard-grid">'
            . '<article class="fp-stat-card"><div class="fp-stat-label">站点名称</div><div class="fp-stat-value">' . $this->escape((string) $siteName) . '</div></article>'
            . '<article class="fp-stat-card"><div class="fp-stat-label">当前登录</div><div class="fp-stat-value">' . $this->escape((string) ($user?->display_name ?: $user?->username ?: '')) . '</div></article>'
            . '<article class="fp-stat-card"><div class="fp-stat-label">时区</div><div class="fp-stat-value">' . $this->escape((string) $this->app->settings->get('site_timezone', 'UTC')) . '</div></article>'
            . '</div>'
            . '</section>'
            . '<section class="panel">'
            . '<h2>常用入口</h2>'
            . '<div class="fp-shortcuts">'
            . $shortcutHtml
            . '</div>'
            . '</section>';

        return $this->html($this->adminShell('仪表盘', $body));
    }
}
