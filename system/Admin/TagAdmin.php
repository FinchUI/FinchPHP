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
        $lang = $this->app->lang;
        $saved = $this->request->query('saved') === '1' ? '<div class="fp-notice-success">' . $this->escape($lang->get('admin.message.saved')) . '</div>' : '';

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
                . '<button type="submit" class="secondary fp-btn-compact">' . $this->escape($lang->get('admin.common.delete')) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted fp-table-empty">' . $this->escape($lang->get('admin.tag.empty')) . '</td></tr>';
        }

        $nameError = isset($errors['name']) ? '<div class="fp-error-text">' . $this->escape($errors['name'][0]) . '</div>' : '';

        return '<h1>' . $this->escape($lang->get('admin.tag.title')) . '</h1>'
            . $saved
            . '<form method="post" action="/admin/tags" class="fp-form-grid fp-form-grid-sm">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.common.name')) . '<input name="name"></label>' . $nameError . '</div>'
            . '<div><label class="fp-field">' . $this->escape($lang->get('admin.category.th_slug')) . '<input name="slug"></label></div>'
            . '<button type="submit" class="fp-btn-wide">' . $this->escape($lang->get('admin.tag.save')) . '</button>'
            . '</form>'
            . '<table>'
            . '<thead><tr><th>ID</th><th>' . $this->escape($lang->get('admin.common.name')) . '</th><th>' . $this->escape($lang->get('admin.category.th_slug')) . '</th><th>' . $this->escape($lang->get('admin.tag.th_post_count')) . '</th><th>' . $this->escape($lang->get('admin.common.actions')) . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

}
