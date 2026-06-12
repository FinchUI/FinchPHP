<?php

/**
 * 迁移：内容核心表（见 PROJECT_PLAN §4.4.1）
 * post / category / post_category / tag / post_tag / comment / upload
 */

declare(strict_types=1);

use Finch\Database\Migration;
use Finch\Database\Schema\Blueprint;
use Finch\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        // 文章/页面/自定义类型
        $schema->create('post', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('author_id');
            $t->bigInteger('primary_category_id')->nullable();
            $t->string('title', 255);
            $t->string('slug', 200);
            $t->longText('content');
            $t->string('excerpt', 1000)->nullable();
            $t->string('type', 50)->default('article');
            $t->string('status', 20)->default('draft');
            $t->string('previous_status', 32)->nullable();
            $t->string('comment_status', 20)->default('open');
            $t->boolean('is_top')->default(false);
            $t->integer('view_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
            $t->unique(['slug', 'type'], 'post_slug_type_unique');
            $t->index(['type', 'status', 'published_at'], 'post_type_status_published');
            $t->index(['author_id', 'status'], 'post_author_status');
            $t->index(['primary_category_id', 'status', 'published_at'], 'post_pcat_status_published');
            $t->index('deleted_at', 'post_deleted_at');
        });

        // 分类
        $schema->create('category', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('parent_id')->nullable();
            $t->string('name', 100);
            $t->string('slug', 100);
            $t->text('description')->nullable();
            $t->integer('sort_order')->default(0);
            $t->integer('post_count')->default(0);
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->unique('slug', 'category_slug_unique');
            $t->index(['parent_id', 'sort_order'], 'category_parent_sort');
            $t->index(['status', 'sort_order'], 'category_status_sort');
        });

        // 文章-分类关联
        $schema->create('post_category', function (Blueprint $t): void {
            $t->bigInteger('post_id');
            $t->bigInteger('category_id');
            $t->primary(['post_id', 'category_id']);
            $t->index(['category_id', 'post_id'], 'post_category_cat_post');
        });

        // 标签
        $schema->create('tag', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 100);
            $t->string('slug', 100);
            $t->integer('post_count')->default(0);
            $t->timestamps();
            $t->unique('slug', 'tag_slug_unique');
            $t->index('name', 'tag_name');
            $t->index('post_count', 'tag_post_count');
        });

        // 文章-标签关联
        $schema->create('post_tag', function (Blueprint $t): void {
            $t->bigInteger('post_id');
            $t->bigInteger('tag_id');
            $t->primary(['post_id', 'tag_id']);
            $t->index(['tag_id', 'post_id'], 'post_tag_tag_post');
        });

        // 评论
        $schema->create('comment', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('post_id');
            $t->bigInteger('author_id')->nullable();
            $t->bigInteger('parent_id')->nullable();
            $t->string('author_name', 100)->nullable();
            $t->string('author_email', 100)->nullable();
            $t->text('content');
            $t->string('status', 20)->default('pending');
            $t->string('ip', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
            $t->index(['post_id', 'status'], 'comment_post_status');
            $t->index('parent_id', 'comment_parent');
            $t->index('deleted_at', 'comment_deleted_at');
        });

        // 上传文件
        $schema->create('upload', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('user_id')->nullable();
            $t->bigInteger('post_id')->nullable();
            $t->string('filename', 255);
            $t->string('original_name', 255);
            $t->string('path', 500);
            $t->string('mime_type', 100)->nullable();
            $t->string('extension', 20)->nullable();
            $t->bigInteger('size')->default(0);
            $t->integer('width')->nullable();
            $t->integer('height')->nullable();
            $t->timestamp('created_at');
            $t->index('user_id', 'upload_user');
            $t->index('post_id', 'upload_post');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('upload');
        $schema->drop('comment');
        $schema->drop('post_tag');
        $schema->drop('tag');
        $schema->drop('post_category');
        $schema->drop('category');
        $schema->drop('post');
    }
};
