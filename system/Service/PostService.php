<?php

/**
 * Finch\Service\PostService - 内容查询服务
 *
 * 1.0 写入路径后续统一从此类扩展；当前先提供已发布内容的列表/详情读取，
 * 供 API 与前台模板共用。
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Database;

final class PostService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 已发布文章分页。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function publishedPage(
        int $page = 1,
        int $perPage = 10,
        string $type = 'article',
        string $sort = 'published_at',
        string $order = 'desc',
    ): array {
        $sort = in_array($sort, ['published_at', 'created_at', 'updated_at', 'id', 'view_count'], true) ? $sort : 'published_at';
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        $nowUtc = gmdate('Y-m-d H:i:s');

        $query = $this->db->table('post')
            ->where('type', $type)
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->where('published_at', '<=', $nowUtc)
            ->orderBy($sort, $order)
            ->orderBy('id', 'DESC');

        $pageData = $query->paginate($page, $perPage);

        // 调试日志：排查前台文章不显示
        $rawCount = $this->db->table('post')->where('type', $type)->whereNull('deleted_at')->count();
        $publishCount = $this->db->table('post')->where('type', $type)->where('status', 'publish')->whereNull('deleted_at')->count();
        $futureCount = $this->db->table('post')->where('type', $type)->where('status', 'publish')->whereNull('deleted_at')->where('published_at', '>', $nowUtc)->count();

        error_log(sprintf(
            '[Finch DEBUG] publishedPage: type=%s nowUtc=%s totalRaw=%d totalPublish=%d futurePublish=%d resultTotal=%d resultData=%d',
            $type, $nowUtc, $rawCount, $publishCount, $futureCount, $pageData['total'], count($pageData['data'])
        ));

        $pageData['data'] = array_map(fn (array $row): array => $this->resource($row), $pageData['data']);

        return $pageData;
    }

    /** 按 ID 查找已发布文章。 */
    public function findPublished(int $id): ?array
    {
        $row = $this->db->table('post')
            ->where('id', $id)
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->where('published_at', '<=', gmdate('Y-m-d H:i:s'))
            ->first();

        return $row === null ? null : $this->resource($row, includeContent: true);
    }

    /** 按 slug 查找已发布文章。 */
    public function findPublishedBySlug(string $slug, string $type = 'article'): ?array
    {
        $row = $this->db->table('post')
            ->where('slug', $slug)
            ->where('type', $type)
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->where('published_at', '<=', gmdate('Y-m-d H:i:s'))
            ->first();

        return $row === null ? null : $this->resource($row, includeContent: true);
    }

    /**
     * 分类下的已发布文章分页。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function publishedByCategory(string|int $category, int $page = 1, int $perPage = 10): array
    {
        $categoryRow = $this->findCategory($category);
        if ($categoryRow === null) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 0];
        }

        $prefix = $this->db->prefix();
        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}post p INNER JOIN {$prefix}post_category pc ON pc.post_id = p.id "
            . 'WHERE pc.category_id = ? AND p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ?',
            [(int) $categoryRow['id'], 'publish', 'article', gmdate('Y-m-d H:i:s')],
        );

        $total = (int) ($countRow['aggregate'] ?? 0);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT p.* FROM {$prefix}post p INNER JOIN {$prefix}post_category pc ON pc.post_id = p.id "
            . 'WHERE pc.category_id = ? AND p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            [(int) $categoryRow['id'], 'publish', 'article', gmdate('Y-m-d H:i:s'), $perPage, $offset],
        );

        return [
            'data'      => array_map(fn (array $row): array => $this->resource($row), $rows),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 标签下的已发布文章分页。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function publishedByTag(string|int $tag, int $page = 1, int $perPage = 10): array
    {
        $tagRow = $this->findTag($tag);
        if ($tagRow === null) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 0];
        }

        $prefix = $this->db->prefix();
        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}post p INNER JOIN {$prefix}post_tag pt ON pt.post_id = p.id "
            . 'WHERE pt.tag_id = ? AND p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ?',
            [(int) $tagRow['id'], 'publish', 'article', gmdate('Y-m-d H:i:s')],
        );

        $total = (int) ($countRow['aggregate'] ?? 0);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT p.* FROM {$prefix}post p INNER JOIN {$prefix}post_tag pt ON pt.post_id = p.id "
            . 'WHERE pt.tag_id = ? AND p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            [(int) $tagRow['id'], 'publish', 'article', gmdate('Y-m-d H:i:s'), $perPage, $offset],
        );

        return [
            'data'      => array_map(fn (array $row): array => $this->resource($row), $rows),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 关键词搜索已发布文章。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function searchPublished(string $keyword, int $page = 1, int $perPage = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['data' => [], 'total' => 0, 'page' => max(1, $page), 'per_page' => max(1, $perPage), 'last_page' => 0];
        }

        $prefix = $this->db->prefix();
        $like = '%' . $keyword . '%';
        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)',
            ['publish', 'article', gmdate('Y-m-d H:i:s'), $like, $like, $like],
        );

        $total = (int) ($countRow['aggregate'] ?? 0);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT p.* FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) '
            . 'ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            ['publish', 'article', gmdate('Y-m-d H:i:s'), $like, $like, $like, $perPage, $offset],
        );

        return [
            'data'      => array_map(fn (array $row): array => $this->resource($row), $rows),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function resource(array $row, bool $includeContent = false): array
    {
        $postId = (int) $row['id'];
        $data = [
            'id'             => $postId,
            'author'         => $this->author((int) $row['author_id']),
            'primary_category_id' => isset($row['primary_category_id']) ? (int) $row['primary_category_id'] : null,
            'title'          => (string) $row['title'],
            'slug'           => (string) $row['slug'],
            'excerpt'        => (string) ($row['excerpt'] ?? ''),
            'type'           => (string) $row['type'],
            'status'         => (string) $row['status'],
            'comment_status' => (string) $row['comment_status'],
            'is_top'         => (bool) $row['is_top'],
            'view_count'     => (int) $row['view_count'],
            'published_at'   => $row['published_at'],
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'],
            'categories'     => $this->categories($postId),
            'tags'           => $this->tags($postId),
        ];

        if ($includeContent) {
            $data['content'] = (string) $row['content'];
        }

        return $data;
    }

    /** @return array{id:int,username:string,display_name:string}|null */
    private function author(int $userId): ?array
    {
        $row = $this->db->table('user')->where('id', $userId)->first();
        if ($row === null) {
            return null;
        }

        return [
            'id'           => (int) $row['id'],
            'username'     => (string) $row['username'],
            'display_name' => (string) ($row['display_name'] ?? $row['username']),
        ];
    }

    /** @return list<array{id:int,name:string,slug:string}> */
    private function categories(int $postId): array
    {
        $prefix = $this->db->prefix();
        $rows = $this->db->select(
            "SELECT c.id, c.name, c.slug FROM {$prefix}category c "
            . "INNER JOIN {$prefix}post_category pc ON pc.category_id = c.id "
            . "WHERE pc.post_id = ? ORDER BY c.sort_order ASC, c.id ASC",
            [$postId],
        );

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ], $rows);
    }

    /** @return list<array{id:int,name:string,slug:string}> */
    private function tags(int $postId): array
    {
        $prefix = $this->db->prefix();
        $rows = $this->db->select(
            "SELECT t.id, t.name, t.slug FROM {$prefix}tag t "
            . "INNER JOIN {$prefix}post_tag pt ON pt.tag_id = t.id "
            . "WHERE pt.post_id = ? ORDER BY t.name ASC, t.id ASC",
            [$postId],
        );

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ], $rows);
    }

    /** @return array<string, mixed>|null */
    private function findCategory(string|int $id): ?array
    {
        $query = $this->db->table('category')->where('status', 'active');
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('slug', (string) $id);
        }

        return $query->first();
    }

    /** @return array<string, mixed>|null */
    private function findTag(string|int $id): ?array
    {
        $query = $this->db->table('tag');
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('slug', (string) $id);
        }

        return $query->first();
    }
}
