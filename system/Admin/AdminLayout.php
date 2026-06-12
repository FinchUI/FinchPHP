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
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title>'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:24px;line-height:1.6;background:#f6f8fa;color:#24292f}'
            . '.panel{background:#fff;border:1px solid #d0d7de;border-radius:8px;padding:16px;margin-bottom:16px}'
            . 'table{width:100%;border-collapse:collapse;background:#fff}th,td{border-bottom:1px solid #d0d7de;padding:8px;text-align:left;vertical-align:top}'
            . 'a{color:#0969da;text-decoration:none}.muted{color:#57606a}.actions{display:flex;gap:8px;flex-wrap:wrap}'
            . 'input,textarea,select{width:100%;box-sizing:border-box;padding:8px;border:1px solid #d0d7de;border-radius:6px}'
            . 'button{padding:7px 12px;border:1px solid #1f6feb;background:#1f6feb;color:#fff;border-radius:6px;cursor:pointer}'
            . 'button.secondary{background:#fff;color:#24292f;border-color:#d0d7de}'
            . '</style></head><body>'
            . $this->adminNav()
            . '<main>' . $body . '</main>'
            . '</body></html>';
    }

    private function adminNav(): string
    {
        return '<nav class="panel">'
            . '<div class="actions">'
            . '<a href="/admin">仪表盘</a>'
            . '<a href="/admin/posts">文章</a>'
            . '<a href="/admin/pages">页面</a>'
            . '<a href="/admin/categories">分类</a>'
            . '<a href="/admin/tags">标签</a>'
            . '<a href="/admin/comments">评论</a>'
            . '<a href="/admin/users">用户</a>'
            . '<a href="/admin/tokens">Token</a>'
            . '<a href="/admin/extensions">扩展</a>'
            . '<a href="/admin/uploads">媒体库</a>'
            . '<a href="/admin/settings">设置</a>'
            . '<a href="/">前台</a>'
            . '</div>'
            . $this->logoutBar()
            . '</nav>';
    }

    private function logoutBar(): string
    {
        return '<form method="post" action="/admin/logout" style="margin-top:10px">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<button type="submit" class="secondary">退出登录</button>'
            . '</form>';
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
