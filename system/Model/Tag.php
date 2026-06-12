<?php

/**
 * Finch\Model\Tag - 标签模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class Tag extends BaseModel
{
    protected static string $table = 'tag';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;
}
