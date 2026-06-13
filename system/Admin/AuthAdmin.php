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
        $errorHtml = $error === '' ? '' : '<div style="color:#b42318;margin-bottom:1rem">' . $this->escape($error) . '</div>';

        return '<main style="max-width:360px;margin:8vh auto;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">'
            . '<h1 style="font-size:1.6rem;margin-bottom:1rem">Finch 后台登录</h1>'
            . $errorHtml
            . '<form method="post" action="' . $this->adminUrl('/admin/login') . '" style="display:grid;gap:.8rem">'
            . '<input type="hidden" name="_token" value="' . $this->escape($token) . '">'
            . '<label>用户名<input name="username" autocomplete="username" style="width:100%;box-sizing:border-box;padding:.55rem;margin-top:.25rem"></label>'
            . '<label>密码<input type="password" name="password" autocomplete="current-password" style="width:100%;box-sizing:border-box;padding:.55rem;margin-top:.25rem"></label>'
            . '<label style="display:flex;gap:.45rem;align-items:center"><input type="checkbox" name="remember" value="1">记住我</label>'
            . '<button type="submit" style="padding:.65rem;border:0;background:#1f6feb;color:#fff;cursor:pointer">登录</button>'
            . '</form></main>';
    }

    private function layout(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Finch PHP</title></head>'
            . '<body style="background:#f6f8fa;margin:0">' . $body . '</body></html>';
    }
}
