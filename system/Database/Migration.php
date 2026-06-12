<?php

/**
 * Finch\Database\Migration - 单个迁移基类
 *
 * 子类实现 up()/down()，通过 Schema\Builder 进行结构变更
 * （见 PROJECT_PLAN §4.3 迁移机制）。
 */

declare(strict_types=1);

namespace Finch\Database;

use Finch\Database\Schema\Builder;

abstract class Migration
{
    /** 升级：创建/修改表结构 */
    abstract public function up(Builder $schema): void;

    /** 回滚：撤销 up() 的变更 */
    abstract public function down(Builder $schema): void;
}
