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
            . '<a class="fp-shortcut" href="' . $u('/admin/posts') . '">文章</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/pages') . '">页面</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/categories') . '">分类</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/tags') . '">标签</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/comments') . '">评论</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/users') . '">用户</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/tokens') . '">Token</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/extensions') . '">扩展</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/uploads') . '">媒体库</a>'
            . '<a class="fp-shortcut" href="' . $u('/admin/settings') . '">系统设置</a>'
            . '</div>'
            . '</section>';

        return $this->html($this->adminShell('仪表盘', $body));
    }
}
