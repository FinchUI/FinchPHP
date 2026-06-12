<?php

/**
 * Finch\Admin\UploadAdmin - 后台媒体库
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Asset;
use Finch\Core\Response;
use Finch\Core\Settings;
use Finch\Service\UploadService;
use RuntimeException;

final class UploadAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));

        return $this->html($this->adminShell('媒体库', $this->content($this->service()->page($page, 20))));
    }

    public function store(): Response
    {
        $postIdInput = $this->request->post('post_id');
        $postId = is_numeric($postIdInput) ? (int) $postIdInput : null;
        $userId = isset($this->app->user) && $this->app->user !== null ? (int) $this->app->user->id : null;

        try {
            $this->service()->storeFromRequest($this->request->file('file'), $userId, $postId);
        } catch (RuntimeException $e) {
            $data = $this->service()->page(1, 20);

            return $this->html($this->adminShell('媒体库', $this->content($data, $e->getMessage())), 422);
        }

        return $this->redirect('/admin/uploads?saved=1');
    }

    /**
     * @param array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int} $pageData
     */
    private function content(array $pageData, string $error = ''): string
    {
        $saved = $this->request->query('saved') === '1' ? '<div style="color:#067647">上传成功。</div>' : '';
        $errorHtml = $error !== '' ? '<div style="color:#b42318">' . $this->escape($error) . '</div>' : '';

        $rows = '';
        foreach ($pageData['data'] as $item) {
            $preview = '-';
            if (str_starts_with((string) ($item['mime_type'] ?? ''), 'image/')) {
                $preview = '<img src="' . $this->escape((string) $item['url']) . '" alt="preview" style="width:56px;height:56px;object-fit:cover;border:1px solid #d0d7de;border-radius:4px">';
            }

            $rows .= '<tr>'
                . '<td>' . (int) $item['id'] . '</td>'
                . '<td>' . $preview . '</td>'
                . '<td><a href="' . $this->escape((string) $item['url']) . '" target="_blank">' . $this->escape((string) $item['original_name']) . '</a><div class="muted">' . $this->escape((string) $item['path']) . '</div></td>'
                . '<td>' . $this->escape((string) $item['mime_type']) . '</td>'
                . '<td>' . $this->escape((string) $item['size']) . '</td>'
                . '<td>' . $this->escape((string) ($item['width'] ?? '-')) . ' x ' . $this->escape((string) ($item['height'] ?? '-')) . '</td>'
                . '<td>' . $this->escape((string) $item['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="muted">暂无上传文件</td></tr>';
        }

        return '<section class="panel"><h1>媒体库</h1>'
            . $saved
            . $errorHtml
            . '<form method="post" action="/admin/uploads/store" enctype="multipart/form-data" style="display:grid;gap:10px;max-width:540px;margin-top:10px">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . '<label>文件<input type="file" name="file" required></label>'
            . '<label>关联文章 ID（可空）<input type="number" name="post_id" min="1" placeholder="例如 12"></label>'
            . '<div class="actions"><button type="submit">上传文件</button></div>'
            . '</form>'
            . '</section>'
            . '<section class="panel"><table><thead><tr><th>ID</th><th>预览</th><th>文件</th><th>MIME</th><th>大小(B)</th><th>尺寸</th><th>上传时间</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . $this->pager($pageData)
            . '</section>';
    }

    /**
     * @param array{total:int,page:int,last_page:int} $pageData
     */
    private function pager(array $pageData): string
    {
        $page = (int) $pageData['page'];
        $lastPage = max(1, (int) $pageData['last_page']);

        $parts = ['<div class="actions" style="margin-top:12px">'];
        if ($page > 1) {
            $parts[] = '<a href="/admin/uploads?page=' . ($page - 1) . '">上一页</a>';
        }

        $parts[] = '<span class="muted">第 ' . $page . ' / ' . $lastPage . ' 页，共 ' . (int) $pageData['total'] . ' 条</span>';

        if ($page < $lastPage) {
            $parts[] = '<a href="/admin/uploads?page=' . ($page + 1) . '">下一页</a>';
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }

    private function service(): UploadService
    {
        $asset = isset($this->app->asset) ? $this->app->asset : null;
        $settings = isset($this->app->settings) ? $this->app->settings : null;

        return new UploadService(
            $this->app->db,
            $asset instanceof Asset ? $asset : null,
            $settings instanceof Settings ? $settings : null,
        );
    }
}
