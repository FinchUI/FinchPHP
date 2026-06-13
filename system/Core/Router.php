<?php

/**
 * Finch\Core\Router - 请求路由器
 *
 * 实现 PROJECT_PLAN §4.7 的三级路由：active → rewrite → default。
 * 路由只负责匹配与分发，业务逻辑交给 Controller/Service。
 */

declare(strict_types=1);

namespace Finch\Core;

use InvalidArgumentException;
use RuntimeException;

final class Router
{
    /** @var array<string, array<string, mixed>> */
    private array $routes = [];

    /** @var list<string> */
    private array $order = [];

    private ?Middleware $middleware = null;

    /** 注入中间件管道。 */
    public function setMiddleware(Middleware $middleware): void
    {
        $this->middleware = $middleware;
    }

    /**
     * 注册路由。
     *
     * @param array<string, mixed> $definition
     */
    public function add(string $name, array $definition): self
    {
        $type = (string) ($definition['type'] ?? 'default');
        if (!in_array($type, ['active', 'rewrite', 'default'], true)) {
            throw new InvalidArgumentException("不支持的路由类型：{$type}");
        }

        if (!isset($definition['handler'])) {
            throw new InvalidArgumentException("路由 {$name} 缺少 handler。 ");
        }

        $definition['name'] = $name;
        $definition['type'] = $type;
        $definition['method'] = strtoupper((string) ($definition['method'] ?? 'ANY'));
        $definition['middleware'] = $definition['middleware'] ?? [];
        $definition['params'] = $definition['params'] ?? [];
        $definition['throttle'] = $definition['throttle'] ?? null;

        $this->routes[$name] = $definition;
        if (!in_array($name, $this->order, true)) {
            $this->order[] = $name;
        }

        return $this;
    }

    /** 注册一组默认前台路由。 */
    public function registerDefaults(): void
    {
        $this->add('api_v1_system_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/system',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:system:read'],
            'handler'    => 'Finch\\Api\\V1\\SystemApi::info',
        ]);

        $this->add('api_v1_system_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/system',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:system:read'],
            'handler'    => 'Finch\\Api\\V1\\SystemApi::info',
        ]);

        $this->add('api_v1_posts_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/posts',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:posts:read'],
            'handler'    => 'Finch\\Api\\V1\\PostsApi::list',
        ]);

        $this->add('api_v1_post_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/posts/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:posts:read'],
            'handler'    => 'Finch\\Api\\V1\\PostsApi::show',
        ]);

        $this->add('api_v1_posts_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/posts',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:posts:read'],
            'handler'    => 'Finch\\Api\\V1\\PostsApi::list',
        ]);

        $this->add('api_v1_post_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/posts/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:posts:read'],
            'handler'    => 'Finch\\Api\\V1\\PostsApi::show',
        ]);

        $this->add('api_v1_post_comments_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/posts/{postId}/comments',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:comments:read'],
            'handler'    => 'Finch\\Api\\V1\\CommentsApi::list',
        ]);

        $this->add('api_v1_post_comments_create_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/posts/{postId}/comments',
            'method'     => 'POST,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:comments:create'],
            'handler'    => 'Finch\\Api\\V1\\CommentsApi::store',
        ]);

        $this->add('api_v1_post_comments_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/posts/{postId}/comments',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:comments:read'],
            'handler'    => 'Finch\\Api\\V1\\CommentsApi::list',
        ]);

        $this->add('api_v1_post_comments_create_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/posts/{postId}/comments',
            'method'     => 'POST,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:comments:create'],
            'handler'    => 'Finch\\Api\\V1\\CommentsApi::store',
        ]);

        $this->add('api_v1_categories_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/categories',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:categories:read'],
            'handler'    => 'Finch\\Api\\V1\\CategoriesApi::list',
        ]);

        $this->add('api_v1_category_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/categories/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:categories:read'],
            'handler'    => 'Finch\\Api\\V1\\CategoriesApi::show',
        ]);

        $this->add('api_v1_categories_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/categories',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:categories:read'],
            'handler'    => 'Finch\\Api\\V1\\CategoriesApi::list',
        ]);

        $this->add('api_v1_category_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/categories/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:categories:read'],
            'handler'    => 'Finch\\Api\\V1\\CategoriesApi::show',
        ]);

        $this->add('api_v1_tags_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/tags',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:tags:read'],
            'handler'    => 'Finch\\Api\\V1\\TagsApi::list',
        ]);

        $this->add('api_v1_tag_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/tags/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:tags:read'],
            'handler'    => 'Finch\\Api\\V1\\TagsApi::show',
        ]);

        $this->add('api_v1_tags_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/tags',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:tags:read'],
            'handler'    => 'Finch\\Api\\V1\\TagsApi::list',
        ]);

        $this->add('api_v1_tag_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/tags/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:tags:read'],
            'handler'    => 'Finch\\Api\\V1\\TagsApi::show',
        ]);

        $this->add('api_v1_uploads_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/uploads',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:uploads:read'],
            'handler'    => 'Finch\\Api\\V1\\UploadsApi::list',
        ]);

        $this->add('api_v1_uploads_create_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/uploads',
            'method'     => 'POST,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:uploads:create'],
            'handler'    => 'Finch\\Api\\V1\\UploadsApi::store',
        ]);

        $this->add('api_v1_uploads_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/uploads',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:uploads:read'],
            'handler'    => 'Finch\\Api\\V1\\UploadsApi::list',
        ]);

        $this->add('api_v1_uploads_create_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/uploads',
            'method'     => 'POST,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:uploads:create'],
            'handler'    => 'Finch\\Api\\V1\\UploadsApi::store',
        ]);

        $this->add('api_v1_users_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/users',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::list',
        ]);

        $this->add('api_v1_user_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/users/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::show',
        ]);

        $this->add('api_v1_me_active', [
            'type'       => 'active',
            'key'        => 'api',
            'value'      => 'v1/me',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::me',
        ]);

        $this->add('api_v1_users_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/users',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::list',
        ]);

        $this->add('api_v1_user_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/users/{id}',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::show',
        ]);

        $this->add('api_v1_me_rewrite', [
            'type'       => 'rewrite',
            'pattern'    => '/api/v1/me',
            'method'     => 'GET,OPTIONS',
            'middleware' => ['cors', 'api_auth', 'ability:users:read'],
            'handler'    => 'Finch\\Api\\V1\\UserApi::me',
        ]);

        $this->add('admin_login', [
            'type'    => 'rewrite',
            'pattern' => '/admin/login',
            'method'  => 'GET',
            'handler' => 'Finch\\Admin\\AuthAdmin::login',
        ]);

        $this->add('admin_login_post', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/login',
            'method'     => 'POST',
            'middleware' => ['csrf'],
            'handler'    => 'Finch\\Admin\\AuthAdmin::authenticate',
        ]);

        $this->add('admin_logout', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/logout',
            'method'     => 'POST',
            'middleware' => ['auth', 'csrf'],
            'handler'    => 'Finch\\Admin\\AuthAdmin::logout',
        ]);

        $this->add('admin_dashboard', [
            'type'       => 'rewrite',
            'pattern'    => '/admin',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\DashboardAdmin::index',
        ]);

        $this->add('admin_uploads', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/uploads',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\UploadAdmin::index',
        ]);

        $this->add('admin_uploads_store', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/uploads/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\UploadAdmin::store',
        ]);

        $this->add('admin_settings', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/settings',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\SettingAdmin::index',
        ]);

        $this->add('admin_settings_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/settings',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\SettingAdmin::save',
        ]);

        $this->add('admin_users', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/users',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\UserAdmin::index',
        ]);

        $this->add('admin_users_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/users/save',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\UserAdmin::save',
        ]);

        $this->add('admin_tokens', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tokens',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::index',
        ]);

        $this->add('admin_tokens_issue', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tokens/issue',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::issue',
        ]);

        $this->add('admin_tokens_revoke', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tokens/revoke',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::revoke',
        ]);

        $this->add('admin_extensions', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/extensions',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::index',
        ]);

        $this->add('admin_extensions_plugin', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/extensions/plugin',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::togglePlugin',
        ]);

        $this->add('admin_extensions_plugin_settings', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/extensions/plugin/settings',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::pluginSettings',
        ]);

        $this->add('admin_extensions_plugin_settings_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/extensions/plugin/settings',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::savePluginSettings',
        ]);

        $this->add('admin_posts', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::index',
        ]);

        $this->add('admin_posts_create', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::create',
        ]);

        $this->add('admin_posts_store', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::store',
        ]);

        $this->add('admin_posts_edit', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::edit',
        ]);

        $this->add('admin_posts_update', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::update',
        ]);

        $this->add('admin_posts_trash', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/trash',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::trash',
        ]);

        $this->add('admin_posts_restore', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/posts/restore',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::restore',
        ]);

        $this->add('admin_pages', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::index',
        ]);

        $this->add('admin_pages_create', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::create',
        ]);

        $this->add('admin_pages_store', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::store',
        ]);

        $this->add('admin_pages_edit', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::edit',
        ]);

        $this->add('admin_pages_update', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::update',
        ]);

        $this->add('admin_pages_trash', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/trash',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::trash',
        ]);

        $this->add('admin_pages_restore', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/pages/restore',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::restore',
        ]);

        $this->add('admin_categories', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/categories',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::index',
        ]);

        $this->add('admin_categories_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/categories',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::save',
        ]);

        $this->add('admin_categories_delete', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/categories/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::delete',
        ]);

        $this->add('admin_tags', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tags',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\TagAdmin::index',
        ]);

        $this->add('admin_tags_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tags',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TagAdmin::save',
        ]);

        $this->add('admin_tags_delete', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/tags/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TagAdmin::delete',
        ]);

        $this->add('admin_comments', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/comments',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::index',
        ]);

        $this->add('admin_comments_approve', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/comments/{id}/approve',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::approve',
        ]);

        $this->add('admin_comments_reject', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/comments/{id}/reject',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::reject',
        ]);

        $this->add('admin_comments_delete', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/comments/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::delete',
        ]);

        // ── 模块管理 ──

        $this->add('admin_modules', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/modules',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::index',
        ]);

        $this->add('admin_modules_toggle', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/modules/toggle',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::toggle',
        ]);

        $this->add('admin_modules_save_order', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/modules/saveOrder',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::saveOrder',
        ]);

        // ── 链接管理中心 ──

        $this->add('admin_links', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::index',
        ]);

        $this->add('admin_links_create', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::create',
        ]);

        $this->add('admin_links_store', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::store',
        ]);

        $this->add('admin_links_edit', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::edit',
        ]);

        $this->add('admin_links_update', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::update',
        ]);

        $this->add('admin_links_delete', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::delete',
        ]);

        $this->add('admin_links_sort', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/sort',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::sort',
        ]);

        $this->add('admin_links_search', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/links/search',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::search',
        ]);

        // ── 主题管理 ──

        $this->add('admin_themes', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/themes',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::index',
        ]);

        $this->add('admin_themes_activate', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/themes/activate',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::activate',
        ]);

        $this->add('admin_themes_preview', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/themes/preview',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::preview',
        ]);

        // ── AI 大模型设置 ──

        $this->add('admin_ai', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/ai',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::index',
        ]);

        $this->add('admin_ai_create', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/ai/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::create',
        ]);

        $this->add('admin_ai_edit', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/ai/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::edit',
        ]);

        $this->add('admin_ai_save', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/ai/save',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\AiAdmin::save',
        ]);

        $this->add('admin_ai_delete', [
            'type'       => 'rewrite',
            'pattern'    => '/admin/ai/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\AiAdmin::delete',
        ]);

        // ── Active 模式回退（无需伪静态，?fp=admin/...） ──

        $this->add('admin_login_active', [
            'type'    => 'active',
            'key'     => 'fp',
            'value'   => 'admin/login',
            'method'  => 'GET',
            'handler' => 'Finch\\Admin\\AuthAdmin::login',
        ]);

        $this->add('admin_login_post_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/login',
            'method'     => 'POST',
            'middleware' => ['csrf'],
            'handler'    => 'Finch\\Admin\\AuthAdmin::authenticate',
        ]);

        $this->add('admin_logout_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/logout',
            'method'     => 'POST',
            'middleware' => ['auth', 'csrf'],
            'handler'    => 'Finch\\Admin\\AuthAdmin::logout',
        ]);

        $this->add('admin_dashboard_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\DashboardAdmin::index',
        ]);

        $this->add('admin_uploads_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/uploads',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\UploadAdmin::index',
        ]);

        $this->add('admin_uploads_store_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/uploads/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\UploadAdmin::store',
        ]);

        $this->add('admin_settings_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/settings',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\SettingAdmin::index',
        ]);

        $this->add('admin_settings_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/settings',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\SettingAdmin::save',
        ]);

        $this->add('admin_users_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/users',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\UserAdmin::index',
        ]);

        $this->add('admin_users_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/users/save',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\UserAdmin::save',
        ]);

        $this->add('admin_tokens_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tokens',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::index',
        ]);

        $this->add('admin_tokens_issue_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tokens/issue',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::issue',
        ]);

        $this->add('admin_tokens_revoke_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tokens/revoke',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TokenAdmin::revoke',
        ]);

        $this->add('admin_extensions_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/extensions',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::index',
        ]);

        $this->add('admin_extensions_plugin_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/extensions/plugin',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::togglePlugin',
        ]);

        $this->add('admin_extensions_plugin_settings_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/extensions/plugin/settings',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::pluginSettings',
        ]);

        $this->add('admin_extensions_plugin_settings_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/extensions/plugin/settings',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ExtensionAdmin::savePluginSettings',
        ]);

        $this->add('admin_posts_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::index',
        ]);

        $this->add('admin_posts_create_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::create',
        ]);

        $this->add('admin_posts_store_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::store',
        ]);

        $this->add('admin_posts_edit_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PostAdmin::edit',
        ]);

        $this->add('admin_posts_update_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::update',
        ]);

        $this->add('admin_posts_trash_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/trash',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::trash',
        ]);

        $this->add('admin_posts_restore_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/posts/restore',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PostAdmin::restore',
        ]);

        $this->add('admin_pages_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::index',
        ]);

        $this->add('admin_pages_create_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::create',
        ]);

        $this->add('admin_pages_store_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::store',
        ]);

        $this->add('admin_pages_edit_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\PageAdmin::edit',
        ]);

        $this->add('admin_pages_update_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::update',
        ]);

        $this->add('admin_pages_trash_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/trash',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::trash',
        ]);

        $this->add('admin_pages_restore_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/pages/restore',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\PageAdmin::restore',
        ]);

        $this->add('admin_categories_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/categories',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::index',
        ]);

        $this->add('admin_categories_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/categories',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::save',
        ]);

        $this->add('admin_categories_delete_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/categories/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CategoryAdmin::delete',
        ]);

        $this->add('admin_tags_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tags',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\TagAdmin::index',
        ]);

        $this->add('admin_tags_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tags',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TagAdmin::save',
        ]);

        $this->add('admin_tags_delete_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/tags/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\TagAdmin::delete',
        ]);

        $this->add('admin_comments_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/comments',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::index',
        ]);

        $this->add('admin_comments_approve_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/comments/{id}/approve',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::approve',
        ]);

        $this->add('admin_comments_reject_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/comments/{id}/reject',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::reject',
        ]);

        $this->add('admin_comments_delete_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/comments/{id}/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\CommentAdmin::delete',
        ]);

        // ── 模块管理 (Active) ──

        $this->add('admin_modules_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/modules',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::index',
        ]);

        $this->add('admin_modules_toggle_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/modules/toggle',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::toggle',
        ]);

        $this->add('admin_modules_save_order_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/modules/saveOrder',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ModuleAdmin::saveOrder',
        ]);

        // ── 链接管理中心 (Active) ──

        $this->add('admin_links_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::index',
        ]);

        $this->add('admin_links_create_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::create',
        ]);

        $this->add('admin_links_store_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/store',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::store',
        ]);

        $this->add('admin_links_edit_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::edit',
        ]);

        $this->add('admin_links_update_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/update',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::update',
        ]);

        $this->add('admin_links_delete_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::delete',
        ]);

        $this->add('admin_links_sort_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/sort',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::sort',
        ]);

        $this->add('admin_links_search_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/links/search',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\LinkAdmin::search',
        ]);

        // ── 主题管理 (Active) ──

        $this->add('admin_themes_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/themes',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::index',
        ]);

        $this->add('admin_themes_activate_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/themes/activate',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::activate',
        ]);

        $this->add('admin_themes_preview_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/themes/preview',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\ThemeAdmin::preview',
        ]);

        // ── AI 大模型设置 (Active) ──

        $this->add('admin_ai_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/ai',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::index',
        ]);

        $this->add('admin_ai_create_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/ai/create',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::create',
        ]);

        $this->add('admin_ai_edit_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/ai/edit',
            'method'     => 'GET',
            'middleware' => ['auth', 'role:access_admin'],
            'handler'    => 'Finch\\Admin\\AiAdmin::edit',
        ]);

        $this->add('admin_ai_save_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/ai/save',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\AiAdmin::save',
        ]);

        $this->add('admin_ai_delete_active', [
            'type'       => 'active',
            'key'        => 'fp',
            'value'      => 'admin/ai/delete',
            'method'     => 'POST',
            'middleware' => ['auth', 'role:access_admin', 'csrf'],
            'handler'    => 'Finch\\Admin\\AiAdmin::delete',
        ]);

        $this->add('post_single', [
            'type'    => 'rewrite',
            'pattern' => '/post/{slug}',
            'method'  => 'GET',
            'handler' => 'Finch\\Controller\\PostController::single',
        ]);

        $this->add('page_single', [
            'type'    => 'rewrite',
            'pattern' => '/page/{slug}',
            'method'  => 'GET',
            'handler' => 'Finch\\Controller\\PageController::single',
        ]);

        $this->add('category_list', [
            'type'    => 'rewrite',
            'pattern' => '/category/{slug}',
            'method'  => 'GET',
            'handler' => 'Finch\\Controller\\CategoryController::list',
        ]);

        $this->add('tag_list', [
            'type'    => 'rewrite',
            'pattern' => '/tag/{slug}',
            'method'  => 'GET',
            'handler' => 'Finch\\Controller\\TagController::list',
        ]);

        $this->add('search', [
            'type'    => 'rewrite',
            'pattern' => '/search',
            'method'  => 'GET',
            'handler' => 'Finch\\Controller\\SearchController::index',
        ]);

        $this->add('home', [
            'type'    => 'default',
            'handler' => 'Finch\\Controller\\HomeController::index',
        ]);
    }

    /**
     * 分发当前请求。
     */
    public function dispatch(Request $request): Response
    {
        $match = $this->match($request);
        if ($match === null) {
            return (new Response())->html($this->notFoundPage(), 404);
        }

        $handler = $match['route']['handler'];
        $params = $match['params'];
        $route = $match['route'];
        $destination = fn (Request $request): Response => $this->normalizeResponse(
            $this->callHandler($handler, $request, $params),
        );

        if ($this->middleware !== null && $route['middleware'] !== []) {
            return $this->middleware->handle($request, $route['middleware'], $destination, $route);
        }

        return $destination($request);
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return (new Response())->json($result);
        }

        return (new Response())->html((string) $result);
    }

    /**
     * 匹配路由，按 active → rewrite → default 顺序。
     *
     * @return array{route:array<string,mixed>,params:array<string,mixed>}|null
     */
    public function match(Request $request): ?array
    {
        foreach (['active', 'rewrite', 'default'] as $type) {
            foreach ($this->order as $name) {
                $route = $this->routes[$name];
                if ($route['type'] !== $type || !$this->methodMatches($route, $request)) {
                    continue;
                }

                $params = match ($type) {
                    'active'  => $this->matchActive($route, $request),
                    'rewrite' => $this->matchRewrite($route, $request),
                    default   => [],
                };

                if ($params !== null) {
                    return ['route' => $route, 'params' => $params];
                }
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->routes;
    }

    /** @param array<string, mixed> $route */
    private function methodMatches(array $route, Request $request): bool
    {
        $method = (string) ($route['method'] ?? 'ANY');

        if ($method === 'ANY') {
            return true;
        }

        $methods = preg_split('/[|,]/', $method) ?: [];

        return in_array($request->method(), array_map('trim', $methods), true);
    }

    /**
     * active 路由匹配：?fp=post&id=1。
     *
     * @param array<string, mixed> $route
     * @return array<string, mixed>|null
     */
    private function matchActive(array $route, Request $request): ?array
    {
        $key = (string) ($route['key'] ?? 'fp');
        $value = (string) ($route['value'] ?? $route['name']);

        $params = [];

        if (str_contains($value, '{')) {
            $quoted = preg_quote($value, '#');
            $regex = preg_replace_callback('/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/', static function (array $matches): string {
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $quoted);

            if ($regex === null || !preg_match('#^' . $regex . '$#', (string) $request->query($key, ''), $matches)) {
                return null;
            }

            foreach ($matches as $paramKey => $paramValue) {
                if (is_string($paramKey)) {
                    $params[$paramKey] = rawurldecode((string) $paramValue);
                }
            }
        } elseif ((string) $request->query($key, '') !== $value) {
            return null;
        }

        foreach ((array) $route['params'] as $param) {
            if (is_string($param) && !array_key_exists($param, $params)) {
                $params[$param] = $request->query($param);
            }
        }

        return $params;
    }

    /**
     * rewrite 路由匹配：/post/{slug}.html。
     *
     * @param array<string, mixed> $route
     * @return array<string, mixed>|null
     */
    private function matchRewrite(array $route, Request $request): ?array
    {
        $pattern = (string) ($route['pattern'] ?? '');
        if ($pattern === '') {
            return null;
        }

        $quoted = preg_quote($pattern, '#');
        $regex = preg_replace_callback('/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/', static function (array $matches): string {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $quoted);

        if ($regex === null || !preg_match('#^' . $regex . '$#', $request->path(), $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode((string) $value);
            }
        }

        return $params;
    }

    /**
     * @param callable|string $handler
     * @param array<string, mixed> $params
     */
    private function callHandler(callable|string $handler, Request $request, array $params): mixed
    {
        if (is_string($handler)) {
            if (!str_contains($handler, '::')) {
                throw new RuntimeException("路由 handler 格式无效：{$handler}");
            }
            [$class, $method] = explode('::', $handler, 2);
            if (!class_exists($class)) {
                throw new RuntimeException("路由控制器不存在：{$class}");
            }
            $controller = new $class($request);

            return $controller->{$method}(...$params);
        }

        return $handler($request, $params);
    }

    private function notFoundPage(): string
    {
        return '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="utf-8">'
            . '<title>页面不存在 - Finch PHP</title></head>'
            . '<body style="font-family:sans-serif;padding:3rem">'
            . '<h1>页面不存在</h1><p>请求的地址没有匹配到可用路由。</p></body></html>';
    }
}
