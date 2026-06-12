<?php

/**
 * Finch\Service\PostManageService - 后台内容写入服务
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;

final class PostManageService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    public function page(string $type = 'article', int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $query = $this->db->table('post')->where('type', $type);
        if ($status === 'trash') {
            $query->whereNotNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
            if ($status !== '') {
                $query->where('status', $status);
            }
        }

        $pageData = $query
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate($page, $perPage);

        $pageData['data'] = array_map(fn (array $row): array => $this->present($row), $pageData['data']);

        return $pageData;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->db->table('post')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }

        $data = $this->present($row, includeContent: true);
        $data['category_ids'] = $this->postCategoryIds($id);
        $data['tag_names'] = $this->postTagNames($id);

        return $data;
    }

    public function create(array $input, int $authorId, string $type = 'article'): int
    {
        $id = $this->db->transaction(function () use ($input, $authorId, $type): int {
            $payload = $this->buildPayload($input, $authorId, $type, null);
            $id = (int) $this->db->table('post')->insert($payload);

            if ($type === 'article') {
                $categoryIds = $this->normalizeCategoryIds($input['category_ids'] ?? []);
                $this->syncCategories($id, [], $categoryIds);

                $tagIds = $this->resolveTagIds($this->normalizeTagNames((string) ($input['tags'] ?? '')));
                $this->syncTags($id, [], $tagIds);

                $primary = $categoryIds === [] ? null : $categoryIds[0];
                $this->db->table('post')->where('id', $id)->update(['primary_category_id' => $primary]);
            }

            return $id;
        });

        $this->invalidateContentCache();

        return $id;
    }

    public function update(int $id, array $input, string $type = 'article'): bool
    {
        $existing = $this->db->table('post')->where('id', $id)->first();
        if ($existing === null) {
            return false;
        }

        $ok = $this->db->transaction(function () use ($id, $input, $type, $existing): bool {
            $payload = $this->buildPayload($input, (int) $existing['author_id'], $type, $existing);
            $this->db->table('post')->where('id', $id)->update($payload);

            if ($type === 'article') {
                $oldCategoryIds = $this->postCategoryIds($id);
                $newCategoryIds = $this->normalizeCategoryIds($input['category_ids'] ?? []);
                $this->syncCategories($id, $oldCategoryIds, $newCategoryIds);

                $oldTagIds = $this->postTagIds($id);
                $newTagIds = $this->resolveTagIds($this->normalizeTagNames((string) ($input['tags'] ?? '')));
                $this->syncTags($id, $oldTagIds, $newTagIds);

                $primary = $newCategoryIds === [] ? null : $newCategoryIds[0];
                $this->db->table('post')->where('id', $id)->update(['primary_category_id' => $primary]);
            }

            return true;
        });

        $this->invalidateContentCache();

        return $ok;
    }

    public function moveToTrash(int $id): bool
    {
        $post = $this->db->table('post')->where('id', $id)->whereNull('deleted_at')->first();
        if ($post === null) {
            return false;
        }

        $previous = (string) ($post['status'] ?? 'draft');

        $ok = $this->db->table('post')->where('id', $id)->update([
            'previous_status' => $previous,
            'status' => 'trash',
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]) > 0;

        if ($ok) {
            $this->invalidateContentCache();
        }

        return $ok;
    }

    public function restore(int $id): bool
    {
        $post = $this->db->table('post')->where('id', $id)->whereNotNull('deleted_at')->first();
        if ($post === null) {
            return false;
        }

        $status = (string) ($post['previous_status'] ?? 'draft');

        $ok = $this->db->table('post')->where('id', $id)->update([
            'status' => $status,
            'previous_status' => null,
            'deleted_at' => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]) > 0;

        if ($ok) {
            $this->invalidateContentCache();
        }

        return $ok;
    }

    private function invalidateContentCache(): void
    {
        (new Cache($this->db))->flushGroups(['home', 'post', 'taxonomy', 'search', 'comment']);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function buildPayload(array $input, int $authorId, string $type, ?array $existing): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $title = trim((string) ($input['title'] ?? ''));
        $content = (string) ($input['content'] ?? '');
        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        if ($excerpt === '') {
            $excerpt = $this->excerpt($content);
        }

        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'publish', 'pending', 'private'], true)) {
            $status = 'draft';
        }

        $commentStatus = (string) ($input['comment_status'] ?? 'open');
        if (!in_array($commentStatus, ['open', 'closed'], true)) {
            $commentStatus = 'open';
        }

        $slugSeed = trim((string) ($input['slug'] ?? ''));
        if ($slugSeed === '') {
            $slugSeed = $title;
        }
        $slug = $this->uniqueSlug($slugSeed, $type, $existing !== null ? (int) $existing['id'] : null);

        $publishedAt = (string) ($input['published_at'] ?? '');
        if ($status === 'publish') {
            if ($publishedAt === '') {
                $publishedAt = $existing !== null && (string) ($existing['published_at'] ?? '') !== ''
                    ? (string) $existing['published_at']
                    : $now;
            }
        } else {
            $publishedAt = $existing !== null ? (string) ($existing['published_at'] ?? '') : '';
            if ($publishedAt === '') {
                $publishedAt = null;
            }
        }

        return [
            'author_id' => $authorId,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'type' => $type,
            'status' => $status,
            'comment_status' => $commentStatus,
            'is_top' => ($input['is_top'] ?? '') === '1' ? 1 : 0,
            'published_at' => $publishedAt,
            'updated_at' => $now,
            'created_at' => $existing !== null ? (string) $existing['created_at'] : $now,
            'deleted_at' => $existing['deleted_at'] ?? null,
        ];
    }

    /** @return list<int> */
    private function normalizeCategoryIds(mixed $value): array
    {
        $list = is_array($value) ? $value : [];
        $ids = [];
        foreach ($list as $item) {
            if (!is_numeric($item)) {
                continue;
            }
            $id = (int) $item;
            if ($id <= 0) {
                continue;
            }
            $exists = $this->db->table('category')->where('id', $id)->where('status', 'active')->exists();
            if ($exists) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    private function normalizeTagNames(string $input): array
    {
        $parts = preg_split('/[,，\n\r]+/u', $input) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name !== '') {
                $items[] = mb_substr($name, 0, 100);
            }
        }

        return array_values(array_unique($items));
    }

    /** @param list<string> $names @return list<int> */
    private function resolveTagIds(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $slug = $this->uniqueTagSlug($name);
            $existing = $this->db->table('tag')->where('slug', $slug)->first();
            if ($existing !== null) {
                $ids[] = (int) $existing['id'];
                continue;
            }

            $id = (int) $this->db->table('tag')->insert([
                'name' => $name,
                'slug' => $slug,
                'post_count' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $old @param list<int> $new */
    private function syncCategories(int $postId, array $old, array $new): void
    {
        $this->db->table('post_category')->where('post_id', $postId)->delete();
        foreach ($new as $categoryId) {
            $this->db->table('post_category')->insert([
                'post_id' => $postId,
                'category_id' => $categoryId,
            ]);
        }

        $this->refreshCategoryCounts(array_values(array_unique(array_merge($old, $new))));
    }

    /** @param list<int> $old @param list<int> $new */
    private function syncTags(int $postId, array $old, array $new): void
    {
        $this->db->table('post_tag')->where('post_id', $postId)->delete();
        foreach ($new as $tagId) {
            $this->db->table('post_tag')->insert([
                'post_id' => $postId,
                'tag_id' => $tagId,
            ]);
        }

        $this->refreshTagCounts(array_values(array_unique(array_merge($old, $new))));
    }

    /** @param list<int> $ids */
    private function refreshCategoryCounts(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $prefix = $this->db->prefix();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $row = $this->db->selectOne(
                "SELECT COUNT(*) AS aggregate FROM {$prefix}post_category pc "
                . "INNER JOIN {$prefix}post p ON p.id = pc.post_id "
                . 'WHERE pc.category_id = ? AND p.type = ? AND p.status = ? AND p.deleted_at IS NULL AND p.published_at <= ?',
                [$id, 'article', 'publish', $now],
            );
            $count = (int) ($row['aggregate'] ?? 0);
            $this->db->table('category')->where('id', $id)->update(['post_count' => $count]);
        }
    }

    /** @param list<int> $ids */
    private function refreshTagCounts(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $prefix = $this->db->prefix();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $row = $this->db->selectOne(
                "SELECT COUNT(*) AS aggregate FROM {$prefix}post_tag pt "
                . "INNER JOIN {$prefix}post p ON p.id = pt.post_id "
                . 'WHERE pt.tag_id = ? AND p.type = ? AND p.status = ? AND p.deleted_at IS NULL AND p.published_at <= ?',
                [$id, 'article', 'publish', $now],
            );
            $count = (int) ($row['aggregate'] ?? 0);
            $this->db->table('tag')->where('id', $id)->update(['post_count' => $count]);
        }
    }

    private function uniqueSlug(string $seed, string $type, ?int $exceptId): string
    {
        $base = $this->slugify($seed, $type === 'page' ? 'page' : 'post');
        $slug = $base;
        $index = 2;

        while ($this->postSlugExists($slug, $type, $exceptId)) {
            $suffix = '-' . $index;
            $slug = mb_substr($base, 0, max(1, 180 - mb_strlen($suffix))) . $suffix;
            $index++;
        }

        return $slug;
    }

    private function postSlugExists(string $slug, string $type, ?int $exceptId): bool
    {
        $query = $this->db->table('post')->where('slug', $slug)->where('type', $type);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function uniqueTagSlug(string $name): string
    {
        $base = $this->slugify($name, 'tag');
        $slug = $base;
        $index = 2;

        while ($this->db->table('tag')->where('slug', $slug)->exists()) {
            $suffix = '-' . $index;
            $slug = mb_substr($base, 0, max(1, 90 - mb_strlen($suffix))) . $suffix;
            $index++;
        }

        return $slug;
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

        return mb_substr($text, 0, 180);
    }

    private function excerpt(string $content): string
    {
        $plain = trim(strip_tags($content));

        return mb_substr($plain, 0, 200);
    }

    /** @return list<int> */
    private function postCategoryIds(int $postId): array
    {
        $rows = $this->db->table('post_category')->where('post_id', $postId)->get();

        return array_map(static fn (array $row): int => (int) $row['category_id'], $rows);
    }

    /** @return list<int> */
    private function postTagIds(int $postId): array
    {
        $rows = $this->db->table('post_tag')->where('post_id', $postId)->get();

        return array_map(static fn (array $row): int => (int) $row['tag_id'], $rows);
    }

    /** @return list<string> */
    private function postTagNames(int $postId): array
    {
        $prefix = $this->db->prefix();
        $rows = $this->db->select(
            "SELECT t.name FROM {$prefix}tag t INNER JOIN {$prefix}post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name ASC",
            [$postId],
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row, bool $includeContent = false): array
    {
        $data = [
            'id' => (int) $row['id'],
            'author_id' => (int) $row['author_id'],
            'primary_category_id' => isset($row['primary_category_id']) ? (int) $row['primary_category_id'] : null,
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'excerpt' => (string) ($row['excerpt'] ?? ''),
            'type' => (string) $row['type'],
            'status' => (string) $row['status'],
            'previous_status' => (string) ($row['previous_status'] ?? ''),
            'comment_status' => (string) ($row['comment_status'] ?? 'open'),
            'is_top' => (int) ($row['is_top'] ?? 0),
            'view_count' => (int) ($row['view_count'] ?? 0),
            'published_at' => $row['published_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'] ?? null,
        ];

        if ($includeContent) {
            $data['content'] = (string) $row['content'];
        }

        return $data;
    }
}
