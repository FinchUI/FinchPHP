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

    /** 字段类型转换 */
    protected static array $casts = [
        'id' => 'int',
        'author_id' => 'int',
        'primary_category_id' => 'int',
        'is_top' => 'int',
        'view_count' => 'int',
    ];

    /** 允许批量赋值的字段 */
    protected static array $fillable = [
        'author_id', 'primary_category_id', 'title', 'slug',
        'content', 'excerpt', 'type', 'status', 'previous_status',
        'comment_status', 'is_top', 'view_count', 'published_at',
    ];

    public function author(): Query
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function primaryCategory(): Query
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): Query
    {
        return $this->belongsToMany(Category::class, 'post_category', 'post_id', 'category_id');
    }

    public function tags(): Query
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }

    public function comments(): Query
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
