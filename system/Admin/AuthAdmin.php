<?php

/**
 * Finch\Admin\AuthAdmin - 后台登录/退出
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;

final class AuthAdmin extends BaseController
{
    use AdminLayout;

    public function login(): Response
    {
        if ($this->app->isLoggedIn) {
            return $this->redirect('/admin');
        }

        return $this->html($this->layout('后台登录', $this->loginForm()));
    }

    public function authenticate(): Response
    {
        $validator = $this->validate([
            'username' => 'required|max:50',
            'password' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return $this->html($this->layout('后台登录', $this->loginForm('请输入用户名和密码。')), 422);
        }

        $user = $this->app->auth->attempt(
            (string) $this->request->post('username'),
            (string) $this->request->post('password'),
            (string) $this->request->post('remember', '') === '1',
        );

        if ($user === null) {
            return $this->html($this->layout('后台登录', $this->loginForm('用户名或密码错误，或账号暂时被锁定。')), 401);
        }

        return $this->redirect('/admin');
    }

    public function logout(): Response
    {
        $this->app->auth->logout();

        return $this->redirect('/admin/login');
    }

    private function loginForm(string $error = ''): string
    {
        $token = $this->app->session->csrfToken();
        $username = trim((string) $this->request->post('username', ''));
        $remember = (string) $this->request->post('remember', '') === '1';
        $errorHtml = $error === '' ? '' : '<div class="fp-auth-error">' . $this->escape($error) . '</div>';

        return '<main class="fp-auth-wrap">'
            . '<section class="fp-auth-card">'
            . '<div class="fp-auth-head">'
            . '<div class="fp-auth-logo" aria-hidden="true">FP</div>'
            . '<div>'
            . '<h1>后台登录</h1>'
            . '<p>欢迎回来，请登录后继续管理站点内容。</p>'
            . '</div>'
            . '</div>'
            . $errorHtml
            . '<form method="post" action="' . $this->adminUrl('/admin/login') . '" class="fp-auth-form">'
            . '<input type="hidden" name="_token" value="' . $this->escape($token) . '">'
            . '<label class="fp-auth-field">用户名'
            . '<input name="username" autocomplete="username" value="' . $this->escape($username) . '" placeholder="请输入用户名">'
            . '</label>'
            . '<label class="fp-auth-field">密码'
            . '<input type="password" name="password" autocomplete="current-password" placeholder="请输入密码">'
            . '</label>'
            . '<label class="fp-auth-remember"><input type="checkbox" name="remember" value="1" ' . ($remember ? 'checked' : '') . '>记住我</label>'
            . '<button type="submit">登录</button>'
            . '</form>'
            . '<div class="fp-auth-footer"><a href="/">返回前台</a></div>'
            . '</section>'
            . '</main>';
    }

    private function layout(string $title, string $body): string
    {
        $assets = $this->resolveAdminStyleAssets();

        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title>'
            . $this->adminStyleLinks($assets['css'])
            . '</head>'
            . '<body class="fp-auth-body">' . $body . $this->adminScriptTags($assets['js']) . '</body></html>';
    }
}
