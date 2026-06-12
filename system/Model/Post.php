<?php

/**
 * Finch\Model\Post - 文章/页面/自定义内容模型
 */

declare(strict_types=1);

namespace Finch\Model;

use Finch\Database\Query;

final class Post extends BaseModel
{
    protected static string $table = 'post';

    protected static bool $softDeletes = true;

    protected static bool $timestamps = true;

    public function author(): Query
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function primaryCategory(): Query
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }
}
