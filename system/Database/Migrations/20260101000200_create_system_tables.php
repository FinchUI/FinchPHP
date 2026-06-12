<?php

/**
 * 迁移：系统与扩展表（见 PROJECT_PLAN §4.4.3）
 * module / system_setting / theme_setting / plugin_setting / module_setting / cache
 *
 * 注：migration 表由 Migrator 自动创建，不在此声明。
 */

declare(strict_types=1);

use Finch\Database\Migration;
use Finch\Database\Schema\Blueprint;
use Finch\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        // 侧栏模块/官方功能模块启用状态
        $schema->create('module', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 100);
            $t->string('type', 50)->default('widget');
            $t->mediumText('content')->nullable();
            $t->string('sidebar', 50)->nullable();
            $t->integer('position')->default(0);
            $t->json('config')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['sidebar', 'position'], 'module_sidebar_position');
            $t->index('is_active', 'module_active');
        });

        // 系统运行期配置
        $schema->create('system_setting', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 191);
            $t->mediumText('value')->nullable();
            $t->string('type', 20)->default('string');
            $t->boolean('autoload')->default(true);
            $t->timestamp('updated_at');
            $t->unique('name', 'system_setting_name_unique');
            $t->index('autoload', 'system_setting_autoload');
        });

        // 主题配置
        $schema->create('theme_setting', function (Blueprint $t): void {
            $t->id();
            $t->string('theme', 100);
            $t->string('name', 191);
            $t->mediumText('value')->nullable();
            $t->string('type', 20)->default('string');
            $t->boolean('autoload')->default(true);
            $t->timestamp('updated_at');
            $t->unique(['theme', 'name'], 'theme_setting_theme_name_unique');
        });

        // 插件配置
        $schema->create('plugin_setting', function (Blueprint $t): void {
            $t->id();
            $t->string('plugin', 100);
            $t->string('name', 191);
            $t->mediumText('value')->nullable();
            $t->string('type', 20)->default('string');
            $t->boolean('autoload')->default(true);
            $t->timestamp('updated_at');
            $t->unique(['plugin', 'name'], 'plugin_setting_plugin_name_unique');
        });

        // 官方模块配置
        $schema->create('module_setting', function (Blueprint $t): void {
            $t->id();
            $t->string('module', 100);
            $t->string('name', 191);
            $t->mediumText('value')->nullable();
            $t->string('type', 20)->default('string');
            $t->boolean('autoload')->default(true);
            $t->timestamp('updated_at');
            $t->unique(['module', 'name'], 'module_setting_module_name_unique');
        });

        // 数据库缓存表（可选，亦可用文件缓存/Redis）
        $schema->create('cache', function (Blueprint $t): void {
            $t->string('key', 255)->default('');
            $t->mediumText('value');
            $t->timestamp('expired_at');
            $t->primary(['key']);
            $t->index('expired_at', 'cache_expired');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('cache');
        $schema->drop('module_setting');
        $schema->drop('plugin_setting');
        $schema->drop('theme_setting');
        $schema->drop('system_setting');
        $schema->drop('module');
    }
};
