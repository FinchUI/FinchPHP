<?php

/**
 * Finch\Model\Comment - 评论模型
 */

declare(strict_types=1);

namespace Finch\Model;

use Finch\Database\Query;

final class Comment extends BaseModel
{
    protected static string $table = 'comment';

    protected static bool $softDeletes = true;

    protected static bool $timestamps = true;

    public function post(): Query
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function author(): Query
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
