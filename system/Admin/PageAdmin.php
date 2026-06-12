<?php

/**
 * Finch\Admin\PageAdmin - 页面后台管理
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\PostManageService;

final class PageAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $status = trim((string) $this->request->query('status', ''));
        $data = $this->service()->page('page', $page, 20, $status);

        $rows = '';
        foreach ($data['data'] as $item) {
            $rows .= '<tr>'
                . '<td>' . (int) $item['id'] . '</td>'
                . '<td><a href="/admin/pages/edit?id=' . (int) $item['id'] . '">' . $this->escape($item['title']) . '</a><div class="muted">/' . $this->escape($item['slug']) . '</div></td>'
                . '<td>' . $this->escape((string) $item['status']) . '</td>'
                . '<td>' . $this->escape((string) ($item['updated_at'] ?? '')) . '</td>'
                . '<td class="actions">'
                . '<a href="/admin/pages/edit?id=' . (int) $item['id'] . '">编辑</a>'
                . '<form method="post" action="/admin/pages/trash" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . (int) $item['id'] . '">'
                . '<button type="submit" class="secondary">回收站</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $html = '<section class="panel"><h1>页面管理</h1>'
            . '<div class="actions"><a href="/admin/pages/create">新建页面</a><a href="/admin/pages?status=trash">查看回收站</a></div>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>标题</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="5" class="muted">暂无页面</td></tr>' : $rows)
            . '</tbody></table></section>';

        return $this->html($this->adminShell('页面管理', $html));
    }

    public function create(): Response
    {
        return $this->html($this->adminShell('新建页面', $this->form('/admin/pages/store', null)));
    }

    public function store(): Response
    {
        $validator = $this->validate([
            'title' => 'required|max:255',
            'status' => 'required|in:draft,publish,pending,private',
            'comment_status' => 'required|in:open,closed',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->html($this->adminShell('新建页面', $this->form('/admin/pages/store', $this->request->all(), $validator->errors())), 422);
        }

        $id = $this->service()->create($this->request->all(), (int) $this->app->user->id, 'page');

        return $this->redirect('/admin/pages/edit?id=' . $id . '&saved=1');
    }

    public function edit(): Response
    {
        $id = (int) $this->request->query('id', 0);
        $page = $this->service()->find($id);
        if ($page === null || (string) $page['type'] !== 'page') {
            return $this->html($this->adminShell('页面管理', '<section class="panel"><h1>页面不存在</h1></section>'), 404);
        }

        return $this->html($this->adminShell('编辑页面', $this->form('/admin/pages/update', $page, [], $id)));
    }

    public function update(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $validator = $this->validate([
            'title' => 'required|max:255',
            'status' => 'required|in:draft,publish,pending,private',
            'comment_status' => 'required|in:open,closed',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            $item = array_replace((array) $this->service()->find($id), $this->request->all());

            return $this->html($this->adminShell('编辑页面', $this->form('/admin/pages/update', $item, $validator->errors(), $id)), 422);
        }

        $ok = $this->service()->update($id, $this->request->all(), 'page');
        if (!$ok) {
            return $this->html($this->adminShell('编辑页面', '<section class="panel"><h1>页面不存在</h1></section>'), 404);
        }

        return $this->redirect('/admin/pages/edit?id=' . $id . '&saved=1');
    }

    public function trash(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $this->service()->moveToTrash($id);

        return $this->redirect('/admin/pages');
    }

    public function restore(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $this->service()->restore($id);

        return $this->redirect('/admin/pages?status=trash');
    }

    /**
     * @param array<string, mixed>|null $item
     * @param array<string, list<string>> $errors
     */
    private function form(string $action, ?array $item, array $errors = [], int $id = 0): string
    {
        $item ??= [
            'title' => '',
            'slug' => '',
            'status' => 'draft',
            'comment_status' => 'closed',
            'excerpt' => '',
            'content' => '',
            'is_top' => 0,
            'published_at' => '',
        ];

        $saved = $this->request->query('saved') === '1' ? '<div style="color:#067647">保存成功。</div>' : '';

        return '<section class="panel"><h1>' . ($id > 0 ? '编辑页面' : '新建页面') . '</h1>' . $saved
            . '<form method="post" action="' . $this->escape($action) . '" style="display:grid;gap:12px">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '')
            . '<label>标题<input name="title" value="' . $this->escape((string) ($item['title'] ?? '')) . '">' . $this->fieldError('title', $errors) . '</label>'
            . '<label>Slug<input name="slug" value="' . $this->escape((string) ($item['slug'] ?? '')) . '"></label>'
            . '<label>状态<select name="status">'
            . $this->option('draft', '草稿', (string) ($item['status'] ?? 'draft'))
            . $this->option('publish', '发布', (string) ($item['status'] ?? 'draft'))
            . $this->option('pending', '待审核', (string) ($item['status'] ?? 'draft'))
            . $this->option('private', '私密', (string) ($item['status'] ?? 'draft'))
            . '</select>' . $this->fieldError('status', $errors) . '</label>'
            . '<label>评论<select name="comment_status">'
            . $this->option('open', '开启', (string) ($item['comment_status'] ?? 'closed'))
            . $this->option('closed', '关闭', (string) ($item['comment_status'] ?? 'closed'))
            . '</select>' . $this->fieldError('comment_status', $errors) . '</label>'
            . '<label>发布时间(UTC)<input name="published_at" value="' . $this->escape((string) ($item['published_at'] ?? '')) . '" placeholder="YYYY-mm-dd HH:ii:ss"></label>'
            . '<label>摘要<textarea name="excerpt" rows="3">' . $this->escape((string) ($item['excerpt'] ?? '')) . '</textarea></label>'
            . '<label>内容<textarea name="content" rows="12">' . $this->escape((string) ($item['content'] ?? '')) . '</textarea></label>'
            . '<div class="actions"><button type="submit">保存</button><a href="/admin/pages">返回列表</a></div>'
            . '</form></section>';
    }

    private function option(string $value, string $label, string $current): string
    {
        return '<option value="' . $this->escape($value) . '" ' . ($value === $current ? 'selected' : '') . '>' . $this->escape($label) . '</option>';
    }

    private function service(): PostManageService
    {
        return new PostManageService($this->app->db);
    }
}
