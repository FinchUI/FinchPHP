<?php

/**
 * Finch\Service\CommentManageService - 后台评论审核服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;

final class CommentManageService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function page(int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $prefix = $this->db->prefix();
        $where = 'c.deleted_at IS NULL';
        $params = [];
        if ($status !== '') {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}comment c WHERE {$where}",
            $params,
        );
        $total = (int) ($countRow['aggregate'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT c.*, p.title AS post_title FROM {$prefix}comment c "
            . "LEFT JOIN {$prefix}post p ON p.id = c.post_id "
            . "WHERE {$where} ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset],
        );

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function approve(int $id): bool
    {
        return $this->setStatus($id, 'approved');
    }

    public function reject(int $id): bool
    {
        return $this->setStatus($id, 'rejected');
    }

    public function delete(int $id): bool
    {
        $ok = $this->db->table('comment')->where('id', $id)->update([
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]) > 0;

        if ($ok) {
            $this->invalidateCommentCache();
        }

        return $ok;
    }

    private function setStatus(int $id, string $status): bool
    {
        $ok = $this->db->table('comment')->where('id', $id)->whereNull('deleted_at')->update([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]) > 0;

        if ($ok) {
            $this->invalidateCommentCache();
        }

        return $ok;
    }

    private function invalidateCommentCache(): void
    {
        (new Cache($this->db))->flushGroups(['comment', 'post', 'home']);
    }
}
