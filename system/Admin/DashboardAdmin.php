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
    public function index(): Response
    {
        $siteName = $this->app->settings->get('site_name', 'Finch');
        $user = $this->app->user;
        $u = fn (string $path): string => $this->adminUrl($path);
        $body = '<h1>仪表盘</h1>'
            . '<p>站点：' . $this->escape($siteName) . '</p>'
            . '<p>当前用户：' . $this->escape($user?->display_name ?: $user?->username ?: '') . '</p>'
            . '<p><a href="' . $u('/admin/posts') . '">文章管理</a> · <a href="' . $u('/admin/pages') . '">页面管理</a> · <a href="' . $u('/admin/categories') . '">分类管理</a> · <a href="' . $u('/admin/tags') . '">标签管理</a> · <a href="' . $u('/admin/comments') . '">评论管理</a> · <a href="' . $u('/admin/users') . '">用户管理</a> · <a href="' . $u('/admin/tokens') . '">Token 管理</a> · <a href="' . $u('/admin/extensions') . '">扩展管理</a> · <a href="' . $u('/admin/uploads') . '">媒体库</a> · <a href="' . $u('/admin/settings') . '">系统设置</a></p>'
            . $this->logoutForm();

        return $this->html($this->layout('仪表盘', $body));
    }

    private function layout(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title></head>'
            . '<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:2rem;line-height:1.6">'
            . '<nav style="margin-bottom:1.5rem"><a href="' . $this->adminUrl('/admin') . '">后台首页</a> · <a href="/">访问站点</a></nav>'
            . $body
            . '</body></html>';
    }

    private function logoutForm(): string
    {
        return '<form method="post" action="' . $this->adminUrl('/admin/logout') . '">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<button type="submit" style="padding:.45rem .75rem;border:1px solid #d0d7de;background:#fff;cursor:pointer">退出登录</button>'
            . '</form>';
    }
}
