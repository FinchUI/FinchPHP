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
        $lang = $this->app->lang;

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
                . '<a href="/admin/pages/edit?id=' . (int) $item['id'] . '">' . $this->escape($lang->get('admin.common.edit')) . '</a>'
                . '<form method="post" action="/admin/pages/trash" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . (int) $item['id'] . '">'
                . '<button type="submit" class="secondary">' . $this->escape($lang->get('admin.post.trash')) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $html = '<section class="panel"><h1>' . $this->escape($lang->get('admin.page.title')) . '</h1>'
            . '<div class="actions"><a href="/admin/pages/create">' . $this->escape($lang->get('admin.page.create')) . '</a><a href="/admin/pages?status=trash">' . $this->escape($lang->get('admin.post.view_trash')) . '</a></div>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>' . $this->escape($lang->get('admin.post.th_title')) . '</th><th>' . $this->escape($lang->get('admin.post.status')) . '</th><th>' . $this->escape($lang->get('admin.post.th_updated_at')) . '</th><th>' . $this->escape($lang->get('admin.common.actions')) . '</th></tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="5" class="muted">' . $this->escape($lang->get('admin.page.empty')) . '</td></tr>' : $rows)
            . '</tbody></table></section>';

        return $this->html($this->adminShell($lang->get('admin.page.title'), $html));
    }

    public function create(): Response
    {
        $lang = $this->app->lang;

        return $this->html($this->adminShell($lang->get('admin.page.create'), $this->form('/admin/pages/store', null)));
    }

    public function store(): Response
    {
        $lang = $this->app->lang;

        $validator = $this->validate([
            'title' => 'required|max:255',
            'status' => 'required|in:draft,publish,pending,private',
            'comment_status' => 'required|in:open,closed',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->html($this->adminShell($lang->get('admin.page.create'), $this->form('/admin/pages/store', $this->request->all(), $validator->errors())), 422);
        }

        $id = $this->service()->create($this->request->all(), (int) $this->app->user->id, 'page');

        return $this->redirect('/admin/pages/edit?id=' . $id . '&saved=1');
    }

    public function edit(): Response
    {
        $lang = $this->app->lang;

        $id = (int) $this->request->query('id', 0);
        $page = $this->service()->find($id);
        if ($page === null || (string) $page['type'] !== 'page') {
            return $this->html($this->adminShell($lang->get('admin.page.title'), '<section class="panel"><h1>' . $this->escape($lang->get('admin.page.not_found')) . '</h1></section>'), 404);
        }

        return $this->html($this->adminShell($lang->get('admin.page.edit'), $this->form('/admin/pages/update', $page, [], $id)));
    }

    public function update(): Response
    {
        $lang = $this->app->lang;

        $id = (int) $this->request->post('id', 0);
        $validator = $this->validate([
            'title' => 'required|max:255',
            'status' => 'required|in:draft,publish,pending,private',
            'comment_status' => 'required|in:open,closed',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            $item = array_replace((array) $this->service()->find($id), $this->request->all());

            return $this->html($this->adminShell($lang->get('admin.page.edit'), $this->form('/admin/pages/update', $item, $validator->errors(), $id)), 422);
        }

        $ok = $this->service()->update($id, $this->request->all(), 'page');
        if (!$ok) {
            return $this->html($this->adminShell($lang->get('admin.page.edit'), '<section class="panel"><h1>' . $this->escape($lang->get('admin.page.not_found')) . '</h1></section>'), 404);
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
        $lang = $this->app->lang;

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

        $saved = $this->request->query('saved') === '1' ? '<div class="fp-notice-success">' . $this->escape($lang->get('admin.message.saved')) . '</div>' : '';

        return '<section class="panel"><h1>' . ($id > 0 ? $this->escape($lang->get('admin.page.edit')) : $this->escape($lang->get('admin.page.create'))) . '</h1>' . $saved
            . '<form method="post" action="' . $this->escape($action) . '" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '')
            . '<label>' . $this->escape($lang->get('admin.post.th_title')) . '<input name="title" value="' . $this->escape((string) ($item['title'] ?? '')) . '">' . $this->fieldError('title', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.slug')) . '<input name="slug" value="' . $this->escape((string) ($item['slug'] ?? '')) . '"></label>'
            . '<label>' . $this->escape($lang->get('admin.post.status')) . '<select name="status">'
            . $this->option('draft', $lang->get('admin.post.draft'), (string) ($item['status'] ?? 'draft'))
            . $this->option('publish', $lang->get('admin.post.publish'), (string) ($item['status'] ?? 'draft'))
            . $this->option('pending', $lang->get('admin.post.pending'), (string) ($item['status'] ?? 'draft'))
            . $this->option('private', $lang->get('admin.post.private'), (string) ($item['status'] ?? 'draft'))
            . '</select>' . $this->fieldError('status', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.comment_status')) . '<select name="comment_status">'
            . $this->option('open', $lang->get('admin.common.open'), (string) ($item['comment_status'] ?? 'closed'))
            . $this->option('closed', $lang->get('admin.common.closed'), (string) ($item['comment_status'] ?? 'closed'))
            . '</select>' . $this->fieldError('comment_status', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.published_at')) . '<input name="published_at" value="' . $this->escape((string) ($item['published_at'] ?? '')) . '" placeholder="YYYY-mm-dd HH:ii:ss"></label>'
            . '<label>' . $this->escape($lang->get('admin.post.excerpt')) . '<textarea name="excerpt" rows="3">' . $this->escape((string) ($item['excerpt'] ?? '')) . '</textarea></label>'
            . '<div class="fp-editor-field">'
            . '<label>' . $this->escape($lang->get('admin.post.content')) . '</label>'
            . '<div id="fp-quill-editor">' . ($item['content'] ?? '') . '</div>'
            . '<input type="hidden" name="content" id="fp-quill-content">'
            . $this->fieldError('content', $errors)
            . '</div>'
            . '<div class="actions"><button type="submit">' . $this->escape($lang->get('admin.common.save')) . '</button><a href="/admin/pages">' . $this->escape($lang->get('admin.common.back_list')) . '</a></div>'
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
