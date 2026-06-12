<?php

/**
 * Finch\Admin\PostAdmin - 文章后台管理
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\PostManageService;
use Finch\Service\TaxonomyManageService;

final class PostAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $status = trim((string) $this->request->query('status', ''));
        $data = $this->service()->page('article', $page, 20, $status);

        $rows = '';
        foreach ($data['data'] as $post) {
            $rows .= '<tr>'
                . '<td>' . (int) $post['id'] . '</td>'
                . '<td><a href="/admin/posts/edit?id=' . (int) $post['id'] . '">' . $this->escape($post['title']) . '</a><div class="muted">/' . $this->escape($post['slug']) . '</div></td>'
                . '<td>' . $this->escape((string) $post['status']) . '</td>'
                . '<td>' . $this->escape((string) ($post['updated_at'] ?? '')) . '</td>'
                . '<td class="actions">'
                . '<a href="/admin/posts/edit?id=' . (int) $post['id'] . '">编辑</a>'
                . '<form method="post" action="/admin/posts/' . ($status === 'trash' ? 'restore' : 'trash') . '" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . (int) $post['id'] . '">'
                . '<button type="submit" class="secondary">' . ($status === 'trash' ? '恢复' : '回收站') . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $html = '<section class="panel"><h1>文章管理</h1>'
            . '<div class="actions"><a href="/admin/posts/create">新建文章</a><a href="/admin/posts?status=trash">查看回收站</a></div>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>标题</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="5" class="muted">暂无文章</td></tr>' : $rows)
            . '</tbody></table></section>';

        return $this->html($this->adminShell('文章管理', $html));
    }

    public function create(): Response
    {
        return $this->html($this->adminShell('新建文章', $this->form('/admin/posts/store', null)));
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
            return $this->html($this->adminShell('新建文章', $this->form('/admin/posts/store', $this->request->all(), $validator->errors())), 422);
        }

        $id = $this->service()->create($this->request->all(), (int) $this->app->user->id, 'article');

        return $this->redirect('/admin/posts/edit?id=' . $id . '&saved=1');
    }

    public function edit(): Response
    {
        $id = (int) $this->request->query('id', 0);
        $post = $this->service()->find($id);
        if ($post === null || (string) $post['type'] !== 'article') {
            return $this->html($this->adminShell('文章管理', '<section class="panel"><h1>文章不存在</h1></section>'), 404);
        }

        return $this->html($this->adminShell('编辑文章', $this->form('/admin/posts/update', $post, [], $id)));
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
            $post = array_replace((array) $this->service()->find($id), $this->request->all());

            return $this->html($this->adminShell('编辑文章', $this->form('/admin/posts/update', $post, $validator->errors(), $id)), 422);
        }

        $ok = $this->service()->update($id, $this->request->all(), 'article');
        if (!$ok) {
            return $this->html($this->adminShell('编辑文章', '<section class="panel"><h1>文章不存在</h1></section>'), 404);
        }

        return $this->redirect('/admin/posts/edit?id=' . $id . '&saved=1');
    }

    public function trash(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $this->service()->moveToTrash($id);

        return $this->redirect('/admin/posts');
    }

    public function restore(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $this->service()->restore($id);

        return $this->redirect('/admin/posts?status=trash');
    }

    /**
     * @param array<string, mixed>|null $post
     * @param array<string, list<string>> $errors
     */
    private function form(string $action, ?array $post, array $errors = [], int $id = 0): string
    {
        $post ??= [
            'title' => '',
            'slug' => '',
            'status' => 'draft',
            'comment_status' => 'open',
            'excerpt' => '',
            'content' => '',
            'is_top' => 0,
            'published_at' => '',
            'category_ids' => [],
            'tag_names' => [],
        ];

        $categories = $this->taxonomyService()->categories();
        $categoryOptions = '';
        $selectedIds = array_map('intval', is_array($post['category_ids'] ?? null) ? $post['category_ids'] : []);
        foreach ($categories as $category) {
            $cid = (int) $category['id'];
            $categoryOptions .= '<label style="display:flex;gap:6px;align-items:center">'
                . '<input type="checkbox" name="category_ids[]" value="' . $cid . '" ' . (in_array($cid, $selectedIds, true) ? 'checked' : '') . '>'
                . $this->escape((string) $category['name'])
                . '</label>';
        }

        $tags = is_array($post['tag_names'] ?? null)
            ? implode(', ', $post['tag_names'])
            : (string) ($post['tags'] ?? '');
        $saved = $this->request->query('saved') === '1' ? '<div style="color:#067647">保存成功。</div>' : '';

        return '<section class="panel"><h1>' . ($id > 0 ? '编辑文章' : '新建文章') . '</h1>' . $saved
            . '<form method="post" action="' . $this->escape($action) . '" style="display:grid;gap:12px">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '')
            . '<label>标题<input name="title" value="' . $this->escape((string) ($post['title'] ?? '')) . '">' . $this->fieldError('title', $errors) . '</label>'
            . '<label>Slug<input name="slug" value="' . $this->escape((string) ($post['slug'] ?? '')) . '"></label>'
            . '<label>状态<select name="status">'
            . $this->option('draft', '草稿', (string) ($post['status'] ?? 'draft'))
            . $this->option('publish', '发布', (string) ($post['status'] ?? 'draft'))
            . $this->option('pending', '待审核', (string) ($post['status'] ?? 'draft'))
            . $this->option('private', '私密', (string) ($post['status'] ?? 'draft'))
            . '</select>' . $this->fieldError('status', $errors) . '</label>'
            . '<label>评论<select name="comment_status">'
            . $this->option('open', '开启', (string) ($post['comment_status'] ?? 'open'))
            . $this->option('closed', '关闭', (string) ($post['comment_status'] ?? 'open'))
            . '</select>' . $this->fieldError('comment_status', $errors) . '</label>'
            . '<label>是否置顶 <input type="checkbox" name="is_top" value="1" ' . ((int) ($post['is_top'] ?? 0) === 1 ? 'checked' : '') . '></label>'
            . '<label>发布时间(UTC)<input name="published_at" value="' . $this->escape((string) ($post['published_at'] ?? '')) . '" placeholder="YYYY-mm-dd HH:ii:ss"></label>'
            . '<label>摘要<textarea name="excerpt" rows="3">' . $this->escape((string) ($post['excerpt'] ?? '')) . '</textarea></label>'
            . '<label>内容<textarea name="content" rows="12">' . $this->escape((string) ($post['content'] ?? '')) . '</textarea></label>'
            . '<fieldset><legend>分类</legend>' . ($categoryOptions === '' ? '<p class="muted">暂无分类</p>' : $categoryOptions) . '</fieldset>'
            . '<label>标签（逗号分隔）<input name="tags" value="' . $this->escape($tags) . '"></label>'
            . '<div class="actions"><button type="submit">保存</button><a href="/admin/posts">返回列表</a></div>'
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

    private function taxonomyService(): TaxonomyManageService
    {
        return new TaxonomyManageService($this->app->db);
    }
}
