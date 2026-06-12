<?php

/**
 * Finch\Service\TaxonomyManageService - 分类/标签后台写入服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;

final class TaxonomyManageService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->db->table('category')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function tags(): array
    {
        return $this->db->table('tag')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function saveCategory(array $input, ?int $id = null): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slugSeed = trim((string) ($input['slug'] ?? ''));
        if ($slugSeed === '') {
            $slugSeed = $name;
        }

        $slug = $this->uniqueSlug('category', $slugSeed, $id);
        $parentId = is_numeric($input['parent_id'] ?? null) ? (int) $input['parent_id'] : null;
        if ($id !== null && $parentId === $id) {
            $parentId = null;
        }

        $payload = [
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($input['description'] ?? '')),
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'status' => in_array((string) ($input['status'] ?? 'active'), ['active', 'inactive'], true)
                ? (string) $input['status']
                : 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['post_count'] = 0;
            $payload['created_at'] = gmdate('Y-m-d H:i:s');
            $createdId = (int) $this->db->table('category')->insert($payload);
            $this->invalidateTaxonomyCache();

            return $createdId;
        }

        $this->db->table('category')->where('id', $id)->update($payload);
        $this->invalidateTaxonomyCache();

        return $id;
    }

    public function deleteCategory(int $id): bool
    {
        $exists = $this->db->table('category')->where('id', $id)->exists();
        if (!$exists) {
            return false;
        }

        $ok = $this->db->transaction(function () use ($id): bool {
            $this->db->table('post')->where('primary_category_id', $id)->update(['primary_category_id' => null]);
            $this->db->table('post_category')->where('category_id', $id)->delete();
            $this->db->table('category')->where('parent_id', $id)->update(['parent_id' => null]);

            return $this->db->table('category')->where('id', $id)->delete() > 0;
        });

        if ($ok) {
            $this->invalidateTaxonomyCache();
        }

        return $ok;
    }

    public function saveTag(array $input, ?int $id = null): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slugSeed = trim((string) ($input['slug'] ?? ''));
        if ($slugSeed === '') {
            $slugSeed = $name;
        }

        $slug = $this->uniqueSlug('tag', $slugSeed, $id);
        $payload = [
            'name' => $name,
            'slug' => $slug,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $payload['post_count'] = 0;
            $payload['created_at'] = gmdate('Y-m-d H:i:s');
            $createdId = (int) $this->db->table('tag')->insert($payload);
            $this->invalidateTaxonomyCache();

            return $createdId;
        }

        $this->db->table('tag')->where('id', $id)->update($payload);
        $this->invalidateTaxonomyCache();

        return $id;
    }

    public function deleteTag(int $id): bool
    {
        $exists = $this->db->table('tag')->where('id', $id)->exists();
        if (!$exists) {
            return false;
        }

        $ok = $this->db->transaction(function () use ($id): bool {
            $this->db->table('post_tag')->where('tag_id', $id)->delete();

            return $this->db->table('tag')->where('id', $id)->delete() > 0;
        });

        if ($ok) {
            $this->invalidateTaxonomyCache();
        }

        return $ok;
    }

    private function invalidateTaxonomyCache(): void
    {
        (new Cache($this->db))->flushGroups(['taxonomy', 'home', 'post', 'search']);
    }

    private function uniqueSlug(string $table, string $seed, ?int $exceptId): string
    {
        $base = $this->slugify($seed, $table);
        $slug = $base;
        $index = 2;

        while ($this->slugExists($table, $slug, $exceptId)) {
            $suffix = '-' . $index;
            $slug = mb_substr($base, 0, max(1, 90 - mb_strlen($suffix))) . $suffix;
            $index++;
        }

        return $slug;
    }

    private function slugExists(string $table, string $slug, ?int $exceptId): bool
    {
        $query = $this->db->table($table)->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function slugify(string $text, string $fallback): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? '';
        $text = preg_replace('/[^\p{L}\p{N}-]+/u', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($text === '') {
            $text = $fallback;
        }

        return mb_substr($text, 0, 90);
    }
}
