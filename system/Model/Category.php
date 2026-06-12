<?php

/**
 * Finch\Model\Category - 分类模型
 */

declare(strict_types=1);

namespace Finch\Model;

use Finch\Database\Query;

final class Category extends BaseModel
{
    protected static string $table = 'category';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    public function parent(): Query
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
