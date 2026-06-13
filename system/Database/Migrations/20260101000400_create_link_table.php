<?php

/**
 * 迁移：链接管理表（链接管理中心插件）
 * link - 导航栏 / 友情链接 / 自定义链接
 */

declare(strict_types=1);

use Finch\Database\Migration;
use Finch\Database\Schema\Blueprint;
use Finch\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('link', function (Blueprint $t): void {
            $t->id();
            $t->string('group_key', 50)->default('navbar');   // navbar / friend / custom
            $t->bigInteger('parent_id')->nullable();          // 二级菜单的父级 ID
            $t->string('title', 200);
            $t->string('url', 500);
            $t->string('link_type', 30)->default('custom');   // custom / page / category / tag / post
            $t->bigInteger('ref_id')->nullable();             // 关联的 page/category/tag/post ID
            $t->string('target', 10)->default('_self');        // _self / _blank
            $t->string('icon', 100)->nullable();              // 图标 class（如 ti ti-home）
            $t->string('description', 500)->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
            $t->index(['group_key', 'is_active', 'sort_order'], 'link_group_active_sort');
            $t->index('parent_id', 'link_parent');
            $t->index('deleted_at', 'link_deleted_at');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('link');
    }
};
