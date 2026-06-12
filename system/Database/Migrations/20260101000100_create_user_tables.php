<?php

/**
 * 迁移：用户与权限/会话表（见 PROJECT_PLAN §4.4.2 与 IMPLEMENTATION_PLAN §1.2）
 * role / user / session / csrf_token / remember_token / api_token / user_social_account / sms_code
 */

declare(strict_types=1);

use Finch\Database\Migration;
use Finch\Database\Schema\Blueprint;
use Finch\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        // 角色
        $schema->create('role', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 50);
            $t->string('slug', 50);
            $t->text('description')->nullable();
            $t->json('capabilities');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique('name', 'role_name_unique');
            $t->unique('slug', 'role_slug_unique');
        });

        // 用户
        $schema->create('user', function (Blueprint $t): void {
            $t->id();
            $t->string('username', 50);
            $t->string('email', 100);
            $t->string('password', 255);
            $t->string('display_name', 100)->nullable();
            $t->string('avatar_url', 500)->nullable();
            $t->bigInteger('role_id');
            $t->json('capabilities')->nullable();
            $t->string('status', 20)->default('active');
            $t->boolean('is_admin')->default(false);
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->integer('login_fail_count')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->string('password_reset_token', 100)->nullable();
            $t->timestamp('password_reset_expires')->nullable();
            $t->timestamps();
            $t->unique('username', 'user_username_unique');
            $t->unique('email', 'user_email_unique');
            $t->index('role_id', 'user_role');
            $t->index('status', 'user_status');
            $t->index('is_admin', 'user_is_admin');
            $t->index('password_reset_token', 'user_reset_token');
        });

        // 会话
        $schema->create('session', function (Blueprint $t): void {
            $t->string('id', 128)->default('');
            $t->bigInteger('user_id')->nullable();
            $t->string('ip_address', 45);
            $t->text('user_agent')->nullable();
            $t->mediumText('payload');
            $t->timestamp('last_activity');
            $t->primary(['id']);
            $t->index('user_id', 'session_user');
            $t->index('last_activity', 'session_last_activity');
        });

        // CSRF 令牌
        $schema->create('csrf_token', function (Blueprint $t): void {
            $t->string('token', 64)->default('');
            $t->string('ip_address', 45);
            $t->text('user_agent')->nullable();
            $t->timestamp('expires_at');
            $t->primary(['token']);
            $t->index('expires_at', 'csrf_expires');
        });

        // "记住我"令牌（独立表，支持多设备）
        $schema->create('remember_token', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('user_id');
            $t->string('token_hash', 255);
            $t->string('user_agent_hash', 64)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('created_at');
            $t->unique('token_hash', 'remember_token_hash_unique');
            $t->index('user_id', 'remember_token_user');
            $t->index('expires_at', 'remember_token_expires');
        });

        // API Token（面向小程序/App/第三方）
        $schema->create('api_token', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('user_id');
            $t->string('name', 100)->nullable();
            $t->string('token_hash', 255);
            $t->json('abilities')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique('token_hash', 'api_token_hash_unique');
            $t->index('user_id', 'api_token_user');
        });

        // 第三方登录账号绑定
        $schema->create('user_social_account', function (Blueprint $t): void {
            $t->id();
            $t->bigInteger('user_id');
            $t->string('provider', 50);
            $t->string('provider_uid', 191);
            $t->string('union_id', 191)->nullable();
            $t->json('profile')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'provider_uid'], 'social_provider_uid_unique');
            $t->index('user_id', 'social_user');
        });

        // 手机验证码
        $schema->create('sms_code', function (Blueprint $t): void {
            $t->id();
            $t->string('phone', 20);
            $t->string('code', 10);
            $t->string('scene', 30)->default('login');
            $t->boolean('used')->default(false);
            $t->string('ip', 45)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('created_at');
            $t->index(['phone', 'scene'], 'sms_phone_scene');
            $t->index('expires_at', 'sms_expires');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('sms_code');
        $schema->drop('user_social_account');
        $schema->drop('api_token');
        $schema->drop('remember_token');
        $schema->drop('csrf_token');
        $schema->drop('session');
        $schema->drop('user');
        $schema->drop('role');
    }
};
