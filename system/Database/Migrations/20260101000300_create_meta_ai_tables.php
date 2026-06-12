<?php

/**
 * 迁移：元数据与 AI 表（见 PROJECT_PLAN §4.4.3）
 * post_meta / user_meta / ai_model / ai_log
 */

declare(strict_types=1);

use Finch\Database\Migration;
use Finch\Database\Schema\Blueprint;
use Finch\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        // 文章低频扩展字段
        $schema->create('post_meta', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('post_id');
            $t->string('meta_key', 191);
            $t->mediumText('meta_value')->nullable();
            $t->string('meta_type', 20)->default('string');
            $t->boolean('autoload')->default(false);
            $t->timestamps();
            $t->unique(['post_id', 'meta_key'], 'post_meta_post_key_unique');
            $t->index('meta_key', 'post_meta_key');
        });

        // 用户低频扩展字段
        $schema->create('user_meta', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('user_id');
            $t->string('meta_key', 191);
            $t->mediumText('meta_value')->nullable();
            $t->string('meta_type', 20)->default('string');
            $t->boolean('autoload')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'meta_key'], 'user_meta_user_key_unique');
            $t->index('meta_key', 'user_meta_key');
        });

        // AI 大模型配置（OpenAI 协议兼容）
        $schema->create('ai_model', function (Blueprint $t): void {
            $t->id();
            $t->string('platform', 100);
            $t->string('protocol', 50)->default('openai');
            $t->string('base_url', 255)->nullable();
            $t->text('api_key_encrypted')->nullable();
            $t->string('model', 100);
            $t->json('params_json')->nullable();
            $t->boolean('is_default')->default(false);
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->index('status', 'ai_model_status');
            $t->index('is_default', 'ai_model_default');
        });

        // AI 调用摘要日志
        $schema->create('ai_log', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('model_id')->nullable();
            $t->string('model_name', 100)->nullable();
            $t->string('platform_name', 100)->nullable();
            $t->bigInteger('user_id')->nullable();
            $t->string('caller_type', 30)->nullable();
            $t->string('caller_name', 100)->nullable();
            $t->string('scene', 50)->nullable();
            $t->string('status', 20)->default('success');
            $t->integer('prompt_tokens')->default(0);
            $t->integer('completion_tokens')->default(0);
            $t->integer('duration_ms')->default(0);
            $t->string('error_code', 50)->nullable();
            $t->string('request_hash', 64)->nullable();
            $t->timestamp('created_at');
            $t->index('model_id', 'ai_log_model');
            $t->index('created_at', 'ai_log_created');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('ai_log');
        $schema->drop('ai_model');
        $schema->drop('user_meta');
        $schema->drop('post_meta');
    }
};
