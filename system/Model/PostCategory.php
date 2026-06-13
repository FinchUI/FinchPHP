<?php

/**
 * Finch\Model\PostCategory - 文章-分类关联模型
 *
 * 对应 post_category 表（见 PROJECT_PLAN §4.4.1）。
 * 支持 post 与 category 的多对多关系，不保留 is_primary 标志
 * （主分类统一由 post.primary_category_id 承载）。
 */

declare(strict_types=1);

namespace Finch\Model;

final class PostCategory extends BaseModel
{
    protected static string $table = 'post_category';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    /**
     * 同步文章的分类关联（全量替换）。
     *
     * @param int $postId
     * @param list<int> $categoryIds
     */
    public static function syncForPost(int $postId, array $categoryIds): void
    {
        self::db()->table('post_category')->where('post_id', $postId)->delete();
        foreach ($categoryIds as $categoryId) {
            self::db()->table('post_category')->insert([
                'post_id' => $postId,
                'category_id' => $categoryId,
            ]);
        }
    }

    /**
     * 获取文章关联的所有分类 ID。
     *
     * @param int $postId
     * @return list<int>
     */
    public static function categoryIdsForPost(int $postId): array
    {
        $rows = self::db()->table('post_category')->where('post_id', $postId)->get();

        return array_map(static fn (array $row): int => (int) $row['category_id'], $rows);
    }

    /**
     * 获取分类下的所有文章 ID。
     *
     * @param int $categoryId
     * @return list<int>
     */
    public static function postIdsForCategory(int $categoryId): array
    {
        $rows = self::db()->table('post_category')->where('category_id', $categoryId)->get();

        return array_map(static fn (array $row): int => (int) $row['post_id'], $rows);
    }
}
