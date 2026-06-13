<?php

/**
 * Finch\Model\PostTag - 文章-标签关联模型
 *
 * 对应 post_tag 表（见 PROJECT_PLAN §4.4.1）。
 * 高容量多对多关联表，支持标签与文章的双向查询。
 */

declare(strict_types=1);

namespace Finch\Model;

final class PostTag extends BaseModel
{
    protected static string $table = 'post_tag';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;

    /**
     * 同步文章的标签关联（全量替换）。
     *
     * @param int $postId
     * @param list<int> $tagIds
     */
    public static function syncForPost(int $postId, array $tagIds): void
    {
        self::db()->table('post_tag')->where('post_id', $postId)->delete();
        foreach ($tagIds as $tagId) {
            self::db()->table('post_tag')->insert([
                'post_id' => $postId,
                'tag_id' => $tagId,
            ]);
        }
    }

    /**
     * 获取文章关联的所有标签 ID。
     *
     * @param int $postId
     * @return list<int>
     */
    public static function tagIdsForPost(int $postId): array
    {
        $rows = self::db()->table('post_tag')->where('post_id', $postId)->get();

        return array_map(static fn (array $row): int => (int) $row['tag_id'], $rows);
    }

    /**
     * 获取标签下的所有文章 ID。
     *
     * @param int $tagId
     * @return list<int>
     */
    public static function postIdsForTag(int $tagId): array
    {
        $rows = self::db()->table('post_tag')->where('tag_id', $tagId)->get();

        return array_map(static fn (array $row): int => (int) $row['post_id'], $rows);
    }
}
