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
        $items = $this->service()->tags();

        return $this->html($this->adminShell('标签管理', '<section class="panel">' . $this->pageContent($items) . '</section>'));
    }

    public function save(): Response
    {
        $id = is_numeric($this->request->post('id')) ? (int) $this->request->post('id') : null;
        $validator = $this->validate([
            'name' => 'required|max:100',
            'slug' => 'max:100',
        ]);

        if ($validator->fails()) {
            $items = $this->service()->tags();

            return $this->html($this->adminShell('标签管理', '<section class="panel">' . $this->pageContent($items, $validator->errors()) . '</section>'), 422);
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
        $saved = $this->request->query('saved') === '1' ? '<div style="color:#067647;margin:0 0 1rem">保存成功。</div>' : '';

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td>' . (int) $item['id'] . '</td>'
                . '<td>' . $this->escape((string) $item['name']) . '</td>'
                . '<td>' . $this->escape((string) $item['slug']) . '</td>'
                . '<td>' . (int) $item['post_count'] . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/tags/' . (int) $item['id'] . '/delete" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" style="border:1px solid #d0d7de;background:#fff;padding:.25rem .5rem">删除</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#57606a">暂无标签</td></tr>';
        }

        $nameError = isset($errors['name']) ? '<div style="color:#b42318">' . $this->escape($errors['name'][0]) . '</div>' : '';

        return '<h1>标签管理</h1>'
            . $saved
            . '<form method="post" action="/admin/tags" style="display:grid;gap:.8rem;max-width:680px;margin:0 0 1.2rem">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<div><label>名称<input name="name" style="display:block;width:100%;box-sizing:border-box;padding:.5rem;margin-top:.25rem"></label>' . $nameError . '</div>'
            . '<div><label>Slug<input name="slug" style="display:block;width:100%;box-sizing:border-box;padding:.5rem;margin-top:.25rem"></label></div>'
            . '<button type="submit" style="width:max-content;padding:.65rem 1rem;border:0;background:#1f6feb;color:#fff">保存标签</button>'
            . '</form>'
            . '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;background:#fff">'
            . '<thead><tr><th>ID</th><th>名称</th><th>Slug</th><th>文章数</th><th>操作</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

}
