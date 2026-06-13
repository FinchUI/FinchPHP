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
        $lang = $this->app->lang;

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
                . '<a href="/admin/posts/edit?id=' . (int) $post['id'] . '">' . $this->escape($lang->get('admin.common.edit')) . '</a>'
                . '<form method="post" action="/admin/posts/' . ($status === 'trash' ? 'restore' : 'trash') . '" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<input type="hidden" name="id" value="' . (int) $post['id'] . '">'
                . '<button type="submit" class="secondary">' . ($status === 'trash' ? $this->escape($lang->get('admin.post.restore')) : $this->escape($lang->get('admin.post.trash'))) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $html = '<section class="panel"><h1>' . $this->escape($lang->get('admin.post.title')) . '</h1>'
            . '<div class="actions"><a href="/admin/posts/create">' . $this->escape($lang->get('admin.post.create')) . '</a><a href="/admin/posts?status=trash">' . $this->escape($lang->get('admin.post.view_trash')) . '</a></div>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>' . $this->escape($lang->get('admin.post.th_title')) . '</th><th>' . $this->escape($lang->get('admin.post.status')) . '</th><th>' . $this->escape($lang->get('admin.post.th_updated_at')) . '</th><th>' . $this->escape($lang->get('admin.common.actions')) . '</th></tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="5" class="muted">' . $this->escape($lang->get('admin.post.empty')) . '</td></tr>' : $rows)
            . '</tbody></table></section>';

        return $this->html($this->adminShell($lang->get('admin.post.title'), $html));
    }

    public function create(): Response
    {
        $lang = $this->app->lang;

        return $this->html($this->adminShell($lang->get('admin.post.create'), $this->form('/admin/posts/store', null)));
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
            return $this->html($this->adminShell($lang->get('admin.post.create'), $this->form('/admin/posts/store', $this->request->all(), $validator->errors())), 422);
        }

        $id = $this->service()->create($this->request->all(), (int) $this->app->user->id, 'article');

        return $this->redirect('/admin/posts/edit?id=' . $id . '&saved=1');
    }

    public function edit(): Response
    {
        $lang = $this->app->lang;

        $id = (int) $this->request->query('id', 0);
        $post = $this->service()->find($id);
        if ($post === null || (string) $post['type'] !== 'article') {
            return $this->html($this->adminShell($lang->get('admin.post.title'), '<section class="panel"><h1>' . $this->escape($lang->get('admin.post.not_found')) . '</h1></section>'), 404);
        }

        return $this->html($this->adminShell($lang->get('admin.post.edit'), $this->form('/admin/posts/update', $post, [], $id)));
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
            $post = array_replace((array) $this->service()->find($id), $this->request->all());

            return $this->html($this->adminShell($lang->get('admin.post.edit'), $this->form('/admin/posts/update', $post, $validator->errors(), $id)), 422);
        }

        $ok = $this->service()->update($id, $this->request->all(), 'article');
        if (!$ok) {
            return $this->html($this->adminShell($lang->get('admin.post.edit'), '<section class="panel"><h1>' . $this->escape($lang->get('admin.post.not_found')) . '</h1></section>'), 404);
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
        $lang = $this->app->lang;

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
            $categoryOptions .= '<label class="fp-check-row">'
                . '<input type="checkbox" name="category_ids[]" value="' . $cid . '" ' . (in_array($cid, $selectedIds, true) ? 'checked' : '') . '>'
                . $this->escape((string) $category['name'])
                . '</label>';
        }

        $tags = is_array($post['tag_names'] ?? null)
            ? implode(', ', $post['tag_names'])
            : (string) ($post['tags'] ?? '');
        $saved = $this->request->query('saved') === '1' ? '<div class="fp-notice-success">' . $this->escape($lang->get('admin.message.saved')) . '</div>' : '';

        return '<section class="panel"><h1>' . ($id > 0 ? $this->escape($lang->get('admin.post.edit')) : $this->escape($lang->get('admin.post.create'))) . '</h1>' . $saved
            . '<form method="post" action="' . $this->escape($action) . '" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '')
            . '<label>' . $this->escape($lang->get('admin.post.th_title')) . '<input name="title" value="' . $this->escape((string) ($post['title'] ?? '')) . '">' . $this->fieldError('title', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.slug')) . '<input name="slug" value="' . $this->escape((string) ($post['slug'] ?? '')) . '"></label>'
            . '<label>' . $this->escape($lang->get('admin.post.status')) . '<select name="status">'
            . $this->option('draft', $lang->get('admin.post.draft'), (string) ($post['status'] ?? 'draft'))
            . $this->option('publish', $lang->get('admin.post.publish'), (string) ($post['status'] ?? 'draft'))
            . $this->option('pending', $lang->get('admin.post.pending'), (string) ($post['status'] ?? 'draft'))
            . $this->option('private', $lang->get('admin.post.private'), (string) ($post['status'] ?? 'draft'))
            . '</select>' . $this->fieldError('status', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.comment_status')) . '<select name="comment_status">'
            . $this->option('open', $lang->get('admin.common.open'), (string) ($post['comment_status'] ?? 'open'))
            . $this->option('closed', $lang->get('admin.common.closed'), (string) ($post['comment_status'] ?? 'open'))
            . '</select>' . $this->fieldError('comment_status', $errors) . '</label>'
            . '<label>' . $this->escape($lang->get('admin.post.is_top')) . ' <input type="checkbox" name="is_top" value="1" ' . ((int) ($post['is_top'] ?? 0) === 1 ? 'checked' : '') . '></label>'
            . '<label>' . $this->escape($lang->get('admin.post.published_at')) . '<input name="published_at" value="' . $this->escape((string) ($post['published_at'] ?? '')) . '" placeholder="YYYY-mm-dd HH:ii:ss"></label>'
            . '<label>' . $this->escape($lang->get('admin.post.excerpt')) . '<textarea name="excerpt" rows="3">' . $this->escape((string) ($post['excerpt'] ?? '')) . '</textarea></label>'
            . '<div class="fp-editor-field">'
            . '<label>' . $this->escape($lang->get('admin.post.content')) . '</label>'
            . '<div id="fp-quill-editor">' . ($post['content'] ?? '') . '</div>'
            . '<input type="hidden" name="content" id="fp-quill-content">'
            . $this->fieldError('content', $errors)
            . '</div>'
            . '<fieldset><legend>' . $this->escape($lang->get('admin.post.category')) . '</legend>' . ($categoryOptions === '' ? '<p class="muted">' . $this->escape($lang->get('admin.post.no_category')) . '</p>' : $categoryOptions) . '</fieldset>'
            . '<label>' . $this->escape($lang->get('admin.post.tags')) . '<input name="tags" value="' . $this->escape($tags) . '"></label>'
            . '<div class="actions"><button type="submit">' . $this->escape($lang->get('admin.common.save')) . '</button><a href="/admin/posts">' . $this->escape($lang->get('admin.common.back_list')) . '</a></div>'
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
