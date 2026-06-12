<?php

/**
 * Finch\Core\Installer - 安装执行器
 *
 * 在已建立数据库连接的前提下，执行：
 *   1. 迁移（建表）
 *   2. 写入 system_setting 默认值（对齐 PROJECT_PLAN §4.4「配置项总表」）
 *   3. 创建内置角色与首个管理员
 *
 * 安装向导 UI（install/index.php）属阶段 10，此类提供其底层能力，
 * 也供 CLI / 自动化验证调用。
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\Database\Migrator;
use Finch\Model\Role;
use Finch\Model\SystemSetting;
use Finch\Model\User;

final class Installer
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * 执行迁移，返回已应用的迁移名列表。
     *
     * @return list<string>
     */
    public function migrate(): array
    {
        return (new Migrator($this->db, $this->migrationsPath))->run();
    }

    /**
     * 写入 system_setting 默认值（幂等：已存在的项不覆盖）。
     *
     * @param array<string, string> $overrides 安装向导收集的覆盖值（如 site_name/site_url）
     */
    public function seedSettings(array $overrides = []): void
    {
        foreach ($this->defaultSettings() as $name => [$value, $type, $autoload]) {
            if (array_key_exists($name, $overrides)) {
                $value = $overrides[$name];
            }
            if (SystemSetting::value($name) === null) {
                SystemSetting::put($name, $value, $type, $autoload);
            }
        }
    }

    /** 创建内置角色（幂等） */
    public function seedRoles(): void
    {
        $roles = [
            ['name' => '管理员', 'slug' => 'admin', 'caps' => ['*'], 'system' => true],
            ['name' => '编辑',   'slug' => 'editor', 'caps' => ['edit_post', 'publish_post', 'manage_comment'], 'system' => true],
            ['name' => '作者',   'slug' => 'author', 'caps' => ['edit_post', 'publish_post'], 'system' => true],
            ['name' => '订阅者', 'slug' => 'subscriber', 'caps' => ['read'], 'system' => true],
        ];

        foreach ($roles as $role) {
            if (Role::query()->where('slug', $role['slug'])->exists()) {
                continue;
            }
            Role::create([
                'name'         => $role['name'],
                'slug'         => $role['slug'],
                'capabilities' => json_encode($role['caps'], JSON_UNESCAPED_UNICODE),
                'is_system'    => $role['system'] ? 1 : 0,
            ]);
        }
    }

    /**
     * 创建首个管理员（用户名已存在时跳过）。
     */
    public function createAdmin(string $username, string $email, string $password): void
    {
        if (User::query()->where('username', $username)->exists()) {
            return;
        }

        $adminRole = Role::query()->where('slug', 'admin')->first();
        $roleId = $adminRole !== null ? (int) $adminRole['id'] : 1;

        User::create([
            'username'     => $username,
            'email'        => $email,
            'password'     => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $username,
            'role_id'      => $roleId,
            'status'       => 'active',
            'is_admin'     => 1,
        ]);
    }

    /**
     * system_setting 默认值表（对齐 PROJECT_PLAN §4.4「配置项总表」）。
     *
     * @return array<string, array{0:mixed,1:string,2:bool}> name => [value, type, autoload]
     */
    private function defaultSettings(): array
    {
        return [
            // 基本
            'site_name'             => ['Finch', 'string', true],
            'site_subtitle'         => ['', 'string', true],
            'site_url'              => ['', 'string', true],
            'site_description'      => ['', 'string', true],
            'site_keywords'         => ['', 'string', true],
            'admin_language'        => ['zh-cn', 'string', true],
            'site_timezone'         => ['Asia/Shanghai', 'string', true],
            'maintenance_mode'      => [false, 'bool', true],
            // 阅读
            'posts_per_page'        => [10, 'int', true],
            'feed_items'            => [10, 'int', true],
            'default_post_type'     => ['article', 'string', true],
            // 评论
            'comment_enabled'       => [true, 'bool', true],
            'comment_need_audit'    => [true, 'bool', true],
            'comment_guest_allowed' => [true, 'bool', true],
            'comment_login_required' => [false, 'bool', true],
            // 用户
            'registration_enabled'  => [true, 'bool', true],
            'default_role'          => ['subscriber', 'string', true],
            'remember_token_days'   => [30, 'int', true],
            // 固定链接
            'permalink_structure'   => ['/post/{id}', 'string', true],
            'category_base'         => ['category', 'string', true],
            'tag_base'              => ['tag', 'string', true],
            // 邮件（按需读取，不 autoload）
            'mail_from_name'        => ['Finch', 'string', false],
            'mail_from_address'     => ['', 'string', false],
            'smtp_host'             => ['', 'string', false],
            'smtp_port'             => ['', 'string', false],
            'smtp_user'             => ['', 'string', false],
            'smtp_pass'             => ['', 'string', false],
            'smtp_secure'           => ['', 'string', false],
            // API
            'api_enabled'           => [true, 'bool', true],
            'api_cors_origins'      => ['', 'string', false],
            // 外观/编辑器
            'admin_style'           => ['tabler', 'string', true],
            'editor'                => ['quill', 'string', true],
            'active_theme'          => ['default', 'string', true],
            // Provider 当前启用项
            'sms_provider'          => ['notify-sms', 'string', true],
            'mail_provider'         => ['notify-email', 'string', true],
            'captcha_provider'      => ['captcha-gd', 'string', true],
            'search_provider'       => ['search-like', 'string', true],
            'ai_provider'           => ['', 'string', false],
        ];
    }
}
