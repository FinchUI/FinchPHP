<?php

/**
 * Finch\Model\Upload - 上传文件模型
 */

declare(strict_types=1);

namespace Finch\Model;

final class Upload extends BaseModel
{
    protected static string $table = 'upload';

    protected static bool $softDeletes = false;

    protected static bool $timestamps = false;
}
