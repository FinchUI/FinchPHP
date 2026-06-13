<?php

/**
 * Finch\Admin\CategoryAdmin - 分类管理后台
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\TaxonomyManageService;

final class CategoryAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $items = $this->service()->categories();

        return $this->html($this->adminShell('分类管理', '<section class="panel">' . $this->pageContent($items) . '</section>'));
    }

    public function save(): Response
    {
        $id = is_numeric($this->request->post('id')) ? (int) $this->request->post('id') : null;
        $validator = $this->validate([
            'name' => 'required|max:100',
            'slug' => 'max:100',
            'sort_order' => 'numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $items = $this->service()->categories();

            return $this->html($this->adminShell('分类管理', '<section class="panel">' . $this->pageContent($items, $validator->errors()) . '</section>'), 422);
        }

        $this->service()->saveCategory($this->request->all(), $id);

        return $this->redirect('/admin/categories?saved=1');
    }

    public function delete(string|int $id): Response
    {
        if (is_numeric($id)) {
            $this->service()->deleteCategory((int) $id);
        }

        return $this->redirect('/admin/categories');
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
                . '<td>' . $this->escape((string) $item['status']) . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/categories/' . (int) $item['id'] . '/delete" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" class="secondary fp-btn-compact">删除</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted fp-table-empty">暂无分类</td></tr>';
        }

        $nameError = isset($errors['name']) ? '<div class="fp-error-text">' . $this->escape($errors['name'][0]) . '</div>' : '';

        return '<h1>分类管理</h1>'
            . $saved
            . '<form method="post" action="/admin/categories" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<div><label class="fp-field">名称<input name="name"></label>' . $nameError . '</div>'
            . '<div><label class="fp-field">Slug<input name="slug"></label></div>'
            . '<div><label class="fp-field">父分类 ID（可空）<input name="parent_id"></label></div>'
            . '<div><label class="fp-field">描述<textarea name="description" rows="2"></textarea></label></div>'
            . '<div><label class="fp-field">排序<input type="number" name="sort_order" value="0"></label></div>'
            . '<div><label class="fp-field">状态<select name="status"><option value="active">启用</option><option value="inactive">停用</option></select></label></div>'
            . '<button type="submit" class="fp-btn-wide">保存分类</button>'
            . '</form>'
            . '<table>'
            . '<thead><tr><th>ID</th><th>名称</th><th>Slug</th><th>文章数</th><th>状态</th><th>操作</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

}
