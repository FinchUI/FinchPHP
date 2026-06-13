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
        $lang = $this->app->lang;
        $items = $this->service()->categories();

        return $this->html($this->adminShell($lang->get('admin.category.title'), '<section class="panel">' . $this->pageContent($items) . '</section>'));
    }

    public function save(): Response
    {
        $lang = $this->app->lang;
        $id = is_numeric($this->request->post('id')) ? (int) $this->request->post('id') : null;
        $validator = $this->validate([
            'name' => 'required|max:100',
            'slug' => 'max:100',
            'sort_order' => 'numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $items = $this->service()->categories();

            return $this->html($this->adminShell($lang->get('admin.category.title'), '<section class="panel">' . $this->pageContent($items, $validator->errors()) . '</section>'), 422);
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
        $lang = $this->app->lang;
        $saved = $this->request->query('saved') === '1' ? '<div class="fp-notice-success">' . $this->escape($lang->get('admin.message.saved')) . '</div>' : '';

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
                . '<button type="submit" class="secondary fp-btn-compact">' . $this->escape($lang->get('admin.common.delete')) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted fp-table-empty">' . $this->escape($lang->get('admin.category.empty')) . '</td></tr>';
        }

        $nameError = isset($errors['name']) ? '<div class="fp-error-text">' . $this->escape($errors['name'][0]) . '</div>' : '';

        return '<h1>' . $this->escape($lang->get('admin.category.title')) . '</h1>'
            . $saved
            . '<form method="post" action="/admin/categories" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.common.name')) . '<input name="name"></label>' . $nameError . '</div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.category.th_slug')) . '<input name="slug"></label></div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.category.parent_id')) . '<input name="parent_id"></label></div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.category.description')) . '<textarea name="description" rows="2"></textarea></label></div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.category.sort_order')) . '<input type="number" name="sort_order" value="0"></label></div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.common.status')) . '<select name="status"><option value="active">' . $this->escape($lang->get('admin.common.active')) . '</option><option value="inactive">' . $this->escape($lang->get('admin.common.inactive')) . '</option></select></label></div>'
            . '<button type="submit" class="fp-btn-wide">' . $this->escape($lang->get('admin.category.save')) . '</button>'
            . '</form>'
            . '<table>'
            . '<thead><tr><th>ID</th><th>' . $this->escape($lang->get('admin.common.name')) . '</th><th>' . $this->escape($lang->get('admin.category.th_slug')) . '</th><th>' . $this->escape($lang->get('admin.category.th_post_count')) . '</th><th>' . $this->escape($lang->get('admin.common.status')) . '</th><th>' . $this->escape($lang->get('admin.common.actions')) . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

}
