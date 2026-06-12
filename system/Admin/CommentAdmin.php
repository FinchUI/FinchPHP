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
    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $status = trim((string) $this->request->query('status', ''));
        $list = $this->service()->page($page, 20, $status);

        return $this->html($this->layout('评论管理', $this->table($list, $status)));
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
                . '<td style="max-width:320px">' . $this->escape((string) ($item['content'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($item['status'] ?? '')) . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/approve" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" style="border:1px solid #d0d7de;background:#fff;padding:.25rem .5rem">通过</button>'
                . '</form> '
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/reject" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" style="border:1px solid #d0d7de;background:#fff;padding:.25rem .5rem">驳回</button>'
                . '</form> '
                . '<form method="post" action="/admin/comments/' . (int) $item['id'] . '/delete" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
                . '<button type="submit" style="border:1px solid #d0d7de;background:#fff;padding:.25rem .5rem">删除</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="text-align:center;color:#57606a">暂无评论</td></tr>';
        }

        return '<h1>评论管理</h1>'
            . '<p><a href="/admin/comments">全部</a> · <a href="/admin/comments?status=pending">待审核</a> · <a href="/admin/comments?status=approved">已通过</a> · <a href="/admin/comments?status=rejected">已驳回</a></p>'
            . '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;background:#fff">'
            . '<thead><tr><th>ID</th><th>文章</th><th>作者</th><th>内容</th><th>状态</th><th>操作</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function layout(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            . $this->escape($title) . ' - Finch PHP</title></head>'
            . '<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:2rem;line-height:1.6;background:#f6f8fa">'
            . '<nav style="margin-bottom:1rem"><a href="/admin">后台首页</a> · <a href="/admin/posts">文章</a> · <a href="/admin/pages">页面</a> · <a href="/admin/categories">分类</a> · <a href="/admin/tags">标签</a> · <a href="/admin/comments">评论</a></nav>'
            . '<section style="background:#fff;border:1px solid #d0d7de;border-radius:8px;padding:1rem 1.25rem">'
            . $body
            . '</section>'
            . $this->logoutForm()
            . '</body></html>';
    }

    private function logoutForm(): string
    {
        return '<form method="post" action="/admin/logout" style="margin-top:1rem">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<button type="submit" style="padding:.45rem .75rem;border:1px solid #d0d7de;background:#fff;cursor:pointer">退出登录</button>'
            . '</form>';
    }
}
