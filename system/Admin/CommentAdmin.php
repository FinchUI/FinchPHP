<?php

/**
 * Finch\Admin\CommentAdmin - 评论管理后台
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\CommentManageService;

final class CommentAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $status = trim((string) $this->request->query('status', ''));
        $list = $this->service()->page($page, 20, $status);

        return $this->html($this->adminShell('评论管理', '<section class="panel">' . $this->table($list, $status) . '</section>'));
    }

    public function approve(string|int $id): Response
    {
        if (is_numeric($id)) {
            $this->service()->approve((int) $id);
        }

        return $this->redirect('/admin/comments');
    }

    public function reject(string|int $id): Response
    {
        if (is_numeric($id)) {
            $this->service()->reject((int) $id);
        }

        return $this->redirect('/admin/comments');
    }

    public function delete(string|int $id): Response
    {
        if (is_numeric($id)) {
            $this->service()->delete((int) $id);
        }

        return $this->redirect('/admin/comments');
    }

    private function service(): CommentManageService
    {
        return new CommentManageService($this->app->db);
    }

    /**
     * @param array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int} $list
     */
    private function table(array $list, string $status): string
    {
        $rows = '';
        foreach ($list['data'] as $item) {
            $rows .= '<tr>'
                . '<td>' . (int) $item['id'] . '</td>'
                . '<td>' . $this->escape((string) ($item['post_title'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($item['author_name'] ?? '')) . '</td>'
                . '<td class="fp-comment-content">' . $this->escape((string) ($item['content'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($item['status'] ?? '')) . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/approve" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" class="secondary fp-btn-compact">通过</button>'
                . '</form> '
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/reject" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" class="secondary fp-btn-compact">驳回</button>'
                . '</form> '
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/delete" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" class="secondary fp-btn-compact">删除</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted fp-table-empty">暂无评论</td></tr>';
        }

        return '<h1>评论管理</h1>'
            . '<p class="fp-comment-filters"><a href="/admin/comments">全部</a> · <a href="/admin/comments?status=pending">待审核</a> · <a href="/admin/comments?status=approved">已通过</a> · <a href="/admin/comments?status=rejected">已驳回</a></p>'
            . '<table>'
            . '<thead><tr><th>ID</th><th>文章</th><th>作者</th><th>内容</th><th>状态</th><th>操作</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

}
