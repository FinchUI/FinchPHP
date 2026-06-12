<?php

/**
 * Finch\Service\TaxonomyService - 分类与标签读取服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Database;

final class TaxonomyService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function categories(bool $tree = false): array
    {
        $rows = $this->db->table('category')
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $categories = array_map(fn (array $row): array => $this->categoryResource($row), $rows);

        return $tree ? $this->categoryTree($categories) : $categories;
    }

    /** 按 ID 或 slug 查找启用分类。 */
    public function findCategory(string|int $id): ?array
    {
        $query = $this->db->table('category')->where('status', 'active');
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('slug', (string) $id);
        }

        $row = $query->first();

        return $row === null ? null : $this->categoryResource($row);
    }

    /**
     * 标签分页。
     *
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function tagsPage(int $page = 1, int $perPage = 20, string $sort = 'name', string $order = 'asc'): array
    {
        $sort = in_array($sort, ['name', 'post_count', 'created_at', 'updated_at', 'id'], true) ? $sort : 'name';
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

        $pageData = $this->db->table('tag')
            ->orderBy($sort, $order)
            ->orderBy('id', 'ASC')
            ->paginate($page, $perPage);
        $pageData['data'] = array_map(fn (array $row): array => $this->tagResource($row), $pageData['data']);

        return $pageData;
    }

    /** 按 ID 或 slug 查找标签。 */
    public function findTag(string|int $id): ?array
    {
        $query = $this->db->table('tag');
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('slug', (string) $id);
        }

        $row = $query->first();

        return $row === null ? null : $this->tagResource($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function categoryResource(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'parent_id'   => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            'name'        => (string) $row['name'],
            'slug'        => (string) $row['slug'],
            'description' => (string) ($row['description'] ?? ''),
            'sort_order'  => (int) $row['sort_order'],
            'post_count'  => (int) $row['post_count'],
            'created_at'  => $row['created_at'],
            'updated_at'  => $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function tagResource(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'slug'       => (string) $row['slug'],
            'post_count' => (int) $row['post_count'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $categories
     * @return list<array<string, mixed>>
     */
    private function categoryTree(array $categories): array
    {
        $byId = [];
        foreach ($categories as $category) {
            $category['children'] = [];
            $byId[$category['id']] = $category;
        }

        $tree = [];
        foreach (array_keys($byId) as $id) {
            $parentId = $byId[$id]['parent_id'];
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$id];
                continue;
            }

            $tree[] = &$byId[$id];
        }

        return $tree;
    }
}
