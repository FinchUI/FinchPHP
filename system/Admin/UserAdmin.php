<?php

/**
 * Finch\Admin\UserAdmin - 后台用户管理
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\Role;
use Finch\Model\User;

final class UserAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $q = trim((string) $this->request->query('q', ''));

        $query = User::query()->orderBy('id', 'DESC');
        if ($q !== '') {
            $query->where('username', 'LIKE', '%' . $q . '%');
        }

        $pageData = $query->paginate($page, 20);
        $roleMap = $this->roleMap();

        return $this->html($this->adminShell('用户管理', $this->content($pageData, $roleMap, $q)));
    }

    public function save(): Response
    {
        $id = (int) $this->request->post('id', 0);
        if ($id <= 0) {
            return $this->redirect('/admin/users');
        }

        $user = User::find($id);
        if (!$user instanceof User) {
            return $this->redirect('/admin/users');
        }

        $validator = $this->validate([
            'display_name' => 'max:100',
            'email' => 'required|email|max:100',
            'status' => 'required|in:active,disabled,pending',
            'role_id' => 'required|numeric|min:1',
            'password' => 'max:255',
        ]);

        if ($validator->fails()) {
            $page = max(1, (int) $this->request->query('page', 1));
            $q = trim((string) $this->request->query('q', ''));
            $query = User::query()->orderBy('id', 'DESC');
            if ($q !== '') {
                $query->where('username', 'LIKE', '%' . $q . '%');
            }
            $pageData = $query->paginate($page, 20);

            return $this->html($this->adminShell('用户管理', $this->content($pageData, $this->roleMap(), $q, $validator->errors())), 422);
        }

        $email = trim((string) $this->request->post('email', ''));
        if (User::query()->where('email', $email)->where('id', '!=', $id)->exists()) {
            $page = max(1, (int) $this->request->query('page', 1));
            $q = trim((string) $this->request->query('q', ''));
            $query = User::query()->orderBy('id', 'DESC');
            if ($q !== '') {
                $query->where('username', 'LIKE', '%' . $q . '%');
            }
            $pageData = $query->paginate($page, 20);

            return $this->html($this->adminShell('用户管理', $this->content($pageData, $this->roleMap(), $q, [
                'email' => ['邮箱已被其他用户占用。'],
            ])), 422);
        }

        $roleMap = $this->roleMap();
        $roleId = (int) $this->request->post('role_id', 0);
        if (!isset($roleMap[$roleId])) {
            return $this->redirect('/admin/users?saved=0');
        }

        $user->display_name = trim((string) $this->request->post('display_name', ''));
        $user->email = $email;
        $user->status = (string) $this->request->post('status', 'active');
        $user->role_id = $roleId;

        $role = Role::find($roleId);
        $user->is_admin = ($role instanceof Role && (string) ($role->slug ?? '') === 'admin') ? 1 : 0;

        $password = (string) $this->request->post('password', '');
        if ($password !== '') {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }

        $user->save();

        $params = [
            'saved=1',
            'page=' . max(1, (int) $this->request->query('page', 1)),
        ];

        $q = trim((string) $this->request->query('q', ''));
        if ($q !== '') {
            $params[] = 'q=' . rawurlencode($q);
        }

        return $this->redirect('/admin/users?' . implode('&', $params));
    }

    /**
     * @param array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int} $pageData
     * @param array<int,string> $roleMap
     * @param array<string,list<string>> $errors
     */
    private function content(array $pageData, array $roleMap, string $q, array $errors = []): string
    {
        $saved = $this->request->query('saved') === '1' ? '<div style="color:#067647">用户信息已保存。</div>' : '';

        $rows = '';
        foreach ($pageData['data'] as $user) {
            $uid = (int) $user['id'];
            $baseAction = '/admin/users/save?page=' . (int) $pageData['page'];
            if ($q !== '') {
                $baseAction .= '&q=' . rawurlencode($q);
            }

            $roleOptions = '';
            $currentRoleId = (int) ($user['role_id'] ?? 0);
            foreach ($roleMap as $roleId => $roleName) {
                $roleOptions .= '<option value="' . $roleId . '" ' . ($roleId === $currentRoleId ? 'selected' : '') . '>'
                    . $this->escape($roleName) . '</option>';
            }

            $rows .= '<tr>'
                . '<td>' . $uid . '</td>'
                . '<td>' . $this->escape((string) ($user['username'] ?? '')) . '</td>'
                . '<td>'
                . '<form method="post" action="' . $this->escape($baseAction) . '" style="display:grid;gap:8px">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . $uid . '">'
                . '<input type="text" name="display_name" value="' . $this->escape((string) ($user['display_name'] ?? '')) . '" placeholder="显示名称">'
                . '<input type="email" name="email" value="' . $this->escape((string) ($user['email'] ?? '')) . '" placeholder="邮箱">'
                . '<select name="role_id">' . $roleOptions . '</select>'
                . '<select name="status">'
                . $this->option('active', 'active', (string) ($user['status'] ?? 'active'))
                . $this->option('disabled', 'disabled', (string) ($user['status'] ?? 'active'))
                . $this->option('pending', 'pending', (string) ($user['status'] ?? 'active'))
                . '</select>'
                . '<input type="password" name="password" placeholder="留空则不修改密码">'
                . '<button type="submit">保存</button>'
                . '</form>'
                . '</td>'
                . '<td>' . $this->escape((string) ($roleMap[$currentRoleId] ?? '未知角色')) . '</td>'
                . '<td>' . $this->escape((string) ($user['status'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($user['last_login_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted">暂无用户</td></tr>';
        }

        $errorList = '';
        foreach ($errors as $field => $messages) {
            if (!isset($messages[0])) {
                continue;
            }
            $errorList .= '<div style="color:#b42318">' . $this->escape($field . ': ' . $messages[0]) . '</div>';
        }

        return '<section class="panel"><h1>用户管理</h1>'
            . $saved
            . $errorList
            . '<form method="get" action="/admin/users" style="display:flex;gap:8px;max-width:420px;margin:10px 0">'
            . '<input name="q" value="' . $this->escape($q) . '" placeholder="按用户名搜索">'
            . '<button type="submit">搜索</button>'
            . '</form>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>用户名</th><th>编辑</th><th>角色</th><th>状态</th><th>上次登录</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . $this->pager($pageData, $q)
            . '</section>';
    }

    /**
     * @param array{total:int,page:int,last_page:int} $pageData
     */
    private function pager(array $pageData, string $q): string
    {
        $page = (int) $pageData['page'];
        $lastPage = max(1, (int) $pageData['last_page']);

        $parts = ['<div class="actions" style="margin-top:12px">'];
        if ($page > 1) {
            $parts[] = '<a href="' . $this->pagerUrl($page - 1, $q) . '">上一页</a>';
        }

        $parts[] = '<span class="muted">第 ' . $page . ' / ' . $lastPage . ' 页，共 ' . (int) $pageData['total'] . ' 条</span>';

        if ($page < $lastPage) {
            $parts[] = '<a href="' . $this->pagerUrl($page + 1, $q) . '">下一页</a>';
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }

    private function pagerUrl(int $page, string $q): string
    {
        $url = '/admin/users?page=' . $page;
        if ($q !== '') {
            $url .= '&q=' . rawurlencode($q);
        }

        return $url;
    }

    /** @return array<int,string> */
    private function roleMap(): array
    {
        $rows = Role::query()->orderBy('id')->get();
        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = (string) ($row['name'] ?? ('角色' . $id));
        }

        return $map;
    }

    private function option(string $value, string $label, string $current): string
    {
        return '<option value="' . $this->escape($value) . '" ' . ($value === $current ? 'selected' : '') . '>' . $this->escape($label) . '</option>';
    }
}
