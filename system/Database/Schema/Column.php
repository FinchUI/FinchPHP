<?php

/**
 * Finch\Database\Schema\Column - 列定义
 *
 * 由 Blueprint 创建，记录列的类型与修饰符，供 Builder 编译为
 * MySQL / SQLite 各自的 DDL（见 PROJECT_PLAN §4.19.2 字段类型映射）。
 */

declare(strict_types=1);

namespace Finch\Database\Schema;

final class Column
{
    public bool $nullable = false;

    public bool $autoIncrement = false;

    public bool $primary = false;

    public bool $unsigned = false;

    public mixed $default = null;

    public bool $hasDefault = false;

    /**
     * @param string $type 抽象类型：id/bigInteger/integer/tinyInteger/string/text/mediumText/longText/boolean/timestamp/json
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
    ) {
    }

    /** 允许 NULL */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    /** 设置默认值 */
    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    /** 标记为无符号（仅 MySQL 生效） */
    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;

        return $this;
    }
}
