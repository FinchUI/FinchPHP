<?php

/**
 * Finch\Admin\TagAdmin - 标签管理后台
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\TaxonomyManageService;

final class TagAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $items = $this->service()->tags();

        return $this->html($this->adminShell($lang->get('admin.tag.title'), '<section class="panel">' . $this->pageContent($items) . '</section>'));
    }

    public function save(): Response
    {
        $lang = $this->app->lang;
        $id = is_numeric($this->request->post('id')) ? (int) $this->request->post('id') : null;
        $validator = $this->validate([
            'name' => 'required|max:100',
            'slug' => 'max:100',
        ]);

        if ($validator->fails()) {
            $items = $this->service()->tags();

            return $this->html($this->adminShell($lang->get('admin.tag.title'), '<section class="panel">' . $this->pageContent($items, $validator->errors()) . '</section>'), 422);
        }

        $this->service()->saveTag($this->request->all(), $id);

        return $this->redirect('/admin/tags?saved=1');
    }

    public function delete(string|int $id): Response
    {
        if (is_numeric($id)) {
            $this->service()->deleteTag((int) $id);
        }

        return $this->redirect('/admin/tags');
    }

    private function service(): TaxonomyManageService
    {
        return new TaxonomyManageService($this->app->db);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<string,list<string>> $errors
     */
    private function pageContent(array $items, array $errors = []): string
    {
        $saved = $this->request->query('saved') === '1' ? '<div class="fp-notice-success">保存成功。</div>' : '';

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td>' . (int) $item['id'] . '</td>'
                . '<td>' . $this->escape((string) $item['name']) . '</td>'
                . '<td>' . $this->escape((string) $item['slug']) . '</td>'
                . '<td>' . (int) $item['post_count'] . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/tags/' . (int) $item['id'] . '/delete" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" class="secondary fp-btn-compact">删除</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted fp-table-empty">暂无标签</td></tr>';
        }

        $nameError = isset($errors['name']) ? '<div class="fp-error-text">' . $this->escape($errors['name'][0]) . '</div>' : '';

        return '<h1>标签管理</h1>'
            . $saved
            . '<form method="post" action="/admin/tags" class="fp-form-grid fp-form-grid-sm">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<div><label class="fp-field">名称<input name="name"></label>' . $nameError . '</div>'
            . '<div><label class="fp-field">Slug<input name="slug"></label></div>'
            . '<button type="submit" class="fp-btn-wide">保存标签</button>'
            . '</form>'
            . '<table>'
            . '<thead><tr><th>ID</th><th>名称</th><th>Slug</th><th>文章数</th><th>操作</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

}
