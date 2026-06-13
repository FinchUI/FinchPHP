<?php

/**
 * Finch\Admin\ExtensionAdmin - 扩展基础管理页
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\PluginSetting;
use Finch\Service\ModuleService;
use Finch\Service\PluginService;
use Finch\Service\SettingService;
use Finch\Service\ThemeService;
use Throwable;

final class ExtensionAdmin extends BaseController
{
    use AdminLayout;

    /**
     * @var array<string, array<string,mixed>>
     */
    private const BUILTIN_PLUGIN_CONFIGS = [
        'tabler' => [
            'title' => 'admin.extension.tabler_title',
            'description' => 'admin.extension.tabler_desc',
            'binding' => [
                'system_key' => 'admin_style',
                'label' => 'admin.extension.tabler_bind_label',
                'help' => 'admin.extension.tabler_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'brand_title',
                    'label' => 'admin.extension.tabler_brand_title',
                    'type' => 'text',
                    'default' => 'Finch Admin',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'sidebar_compact',
                    'label' => 'admin.extension.tabler_sidebar_compact',
                    'type' => 'checkbox',
                    'default' => false,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'quill' => [
            'title' => 'admin.extension.quill_title',
            'description' => 'admin.extension.quill_desc',
            'binding' => [
                'system_key' => 'editor',
                'label' => 'admin.extension.quill_bind_label',
                'help' => 'admin.extension.quill_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'toolbar_mode',
                    'label' => 'admin.extension.quill_toolbar_mode',
                    'type' => 'select',
                    'options' => [
                        'simple' => 'admin.extension.quill_toolbar_simple',
                        'full' => 'admin.extension.quill_toolbar_full',
                    ],
                    'default' => 'simple',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'placeholder',
                    'label' => 'admin.extension.quill_placeholder',
                    'type' => 'text',
                    'default' => '开始编写内容...',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
            ],
        ],
        'search-like' => [
            'title' => 'admin.extension.search_title',
            'description' => 'admin.extension.search_desc',
            'binding' => [
                'system_key' => 'search_provider',
                'label' => 'admin.extension.search_bind_label',
                'help' => 'admin.extension.search_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'match_title',
                    'label' => 'admin.extension.search_match_title',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'match_excerpt',
                    'label' => 'admin.extension.search_match_excerpt',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'match_content',
                    'label' => 'admin.extension.search_match_content',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'per_page_limit',
                    'label' => 'admin.extension.search_per_page_limit',
                    'type' => 'number',
                    'default' => 50,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
            ],
        ],
        'notify-email' => [
            'title' => 'admin.extension.email_title',
            'description' => 'admin.extension.email_desc',
            'binding' => [
                'system_key' => 'mail_provider',
                'label' => 'admin.extension.email_bind_label',
                'help' => 'admin.extension.email_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'mail_from_name',
                    'label' => 'admin.extension.email_from_name',
                    'type' => 'text',
                    'default' => 'Finch',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'mail_from_name',
                ],
                [
                    'name' => 'mail_from_address',
                    'label' => 'admin.extension.email_from_address',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'mail_from_address',
                ],
                [
                    'name' => 'smtp_host',
                    'label' => 'SMTP Host',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'smtp_host',
                ],
                [
                    'name' => 'smtp_port',
                    'label' => 'SMTP Port',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'smtp_port',
                ],
                [
                    'name' => 'smtp_user',
                    'label' => 'admin.extension.email_smtp_user',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'smtp_user',
                ],
                [
                    'name' => 'smtp_pass',
                    'label' => 'admin.extension.email_smtp_pass',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => 'admin.extension.help_empty_password',
                    'sync_system_key' => 'smtp_pass',
                ],
                [
                    'name' => 'smtp_secure',
                    'label' => 'admin.extension.email_smtp_secure',
                    'type' => 'select',
                    'options' => [
                        '' => 'admin.extension.email_smtp_secure_none',
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ],
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'smtp_secure',
                ],
            ],
        ],
        'notify-sms' => [
            'title' => 'admin.extension.sms_title',
            'description' => 'admin.extension.sms_desc',
            'binding' => [
                'system_key' => 'sms_provider',
                'label' => 'admin.extension.sms_bind_label',
                'help' => 'admin.extension.sms_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'vendor',
                    'label' => 'admin.extension.sms_vendor',
                    'type' => 'text',
                    'default' => 'mock',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
                [
                    'name' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => 'admin.extension.help_empty_key',
                ],
                [
                    'name' => 'api_secret',
                    'label' => 'API Secret',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => 'admin.extension.help_empty_secret',
                ],
                [
                    'name' => 'sign_name',
                    'label' => 'admin.extension.sms_sign_name',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
                [
                    'name' => 'template_code',
                    'label' => 'admin.extension.sms_template_code',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
            ],
        ],
        'captcha-gd' => [
            'title' => 'admin.extension.captcha_title',
            'description' => 'admin.extension.captcha_desc',
            'binding' => [
                'system_key' => 'captcha_provider',
                'label' => 'admin.extension.captcha_bind_label',
                'help' => 'admin.extension.captcha_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'code_length',
                    'label' => 'admin.extension.captcha_code_length',
                    'type' => 'number',
                    'default' => 4,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
                [
                    'name' => 'ttl_seconds',
                    'label' => 'admin.extension.captcha_ttl',
                    'type' => 'number',
                    'default' => 300,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
                [
                    'name' => 'case_sensitive',
                    'label' => 'admin.extension.captcha_case_sensitive',
                    'type' => 'checkbox',
                    'default' => false,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'social-github' => [
            'title' => 'admin.extension.github_title',
            'description' => 'admin.extension.github_desc',
            'fields' => [
                [
                    'name' => 'client_id',
                    'label' => 'Client ID',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
                [
                    'name' => 'client_secret',
                    'label' => 'Client Secret',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => 'admin.extension.help_empty_secret',
                ],
                [
                    'name' => 'redirect_uri',
                    'label' => 'admin.extension.github_redirect_uri',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'auto_create_user',
                    'label' => 'admin.extension.github_auto_create',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'ai-openai' => [
            'title' => 'admin.extension.ai_title',
            'description' => 'admin.extension.ai_desc',
            'binding' => [
                'system_key' => 'ai_provider',
                'label' => 'admin.extension.ai_bind_label',
                'help' => 'admin.extension.ai_bind_help',
            ],
            'fields' => [
                [
                    'name' => 'base_url',
                    'label' => 'admin.extension.ai_base_url',
                    'type' => 'text',
                    'default' => 'https://api.openai.com/v1',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
                [
                    'name' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => 'admin.extension.help_empty_key',
                ],
                [
                    'name' => 'model',
                    'label' => 'admin.extension.ai_model',
                    'type' => 'text',
                    'default' => 'gpt-4o-mini',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'temperature',
                    'label' => 'temperature',
                    'type' => 'text',
                    'default' => '0.7',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'timeout_seconds',
                    'label' => 'admin.extension.ai_timeout',
                    'type' => 'number',
                    'default' => 120,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
            ],
        ],
    ];

    public function index(): Response
    {
        $lang = $this->app->lang;

        return $this->html($this->adminShell($lang->get('admin.extension.title'), $this->content()));
    }

    public function activateTheme(): Response
    {
        $theme = trim((string) $this->request->post('theme', ''));
        $ok = $this->themeService()->activate($theme);

        return $this->redirect('/admin/extensions?theme=' . ($ok ? '1' : '0'));
    }

    public function toggleModule(): Response
    {
        $module = trim((string) $this->request->post('module', ''));
        $enabled = (string) $this->request->post('enabled', '1') === '1';
        $ok = $this->moduleService()->setEnabled($module, $enabled);

        return $this->redirect('/admin/extensions?module=' . ($ok ? '1' : '0'));
    }

    public function togglePlugin(): Response
    {
        $plugin = trim((string) $this->request->post('plugin', ''));
        $enabled = (string) $this->request->post('enabled', '1') === '1';
        $ok = $this->pluginService()->setEnabled($plugin, $enabled);

        return $this->redirect('/admin/extensions?plugin=' . ($ok ? '1' : '0'));
    }

    public function pluginSettings(): Response
    {
        $lang = $this->app->lang;
        $plugin = trim((string) $this->request->query('plugin', ''));
        $meta = $this->pluginMeta($plugin);
        $config = $this->pluginConfig($plugin);

        if ($plugin === '' || $meta === null || $config === null || !(bool) ($meta['preinstalled'] ?? false)) {
            return $this->html($this->adminShell($lang->get('admin.extension.plugin_config'), '<section class="panel"><h1>' . $this->escape($lang->get('admin.extension.plugin_config')) . '</h1><div class="fp-error-text">' . $this->escape($lang->get('admin.extension.plugin_not_found')) . '</div><p><a href="/admin/extensions">' . $this->escape($lang->get('admin.extension.back_extensions')) . '</a></p></section>'), 404);
        }

        $values = $this->pluginSettingsValues($plugin, $config);
        $saved = (string) $this->request->query('saved', '');
        $bind = (string) $this->request->query('bind', '');
        $notice = '';
        if ($saved === '1') {
            $notice .= '<div class="fp-notice-success">' . $this->escape($lang->get('admin.extension.plugin_saved')) . '</div>';
        } elseif ($saved === '0') {
            $notice .= '<div class="fp-error-text">' . $this->escape($lang->get('admin.extension.plugin_save_failed')) . '</div>';
        }

        if ($bind === '0') {
            $notice .= '<div class="fp-notice-warn">' . $this->escape($lang->get('admin.extension.plugin_disabled_warn')) . '</div>';
        }

        $status = (bool) ($meta['enabled'] ?? false) ? '<strong>' . $this->escape($lang->get('admin.common.enable')) . '</strong>' : $this->escape($lang->get('admin.common.disable'));
        $fields = '';
        foreach (($config['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fields .= $this->pluginField($field, $values);
        }

        $bindingBlock = $this->pluginBindingBlock($plugin, $meta, $config, $values);
        $token = $this->escape($this->app->session->csrfToken());

        $body = '<section class="panel"><h1>' . $this->escape($lang->get((string) ($config['title'] ?? $plugin))) . '</h1>'
            . $notice
            . '<p class="muted">' . $this->escape($lang->get((string) ($config['description'] ?? ''))) . '</p>'
            . '<p>' . $this->escape($lang->get('admin.extension.plugin_id')) . '：<strong>' . $this->escape($plugin) . '</strong>；' . $this->escape($lang->get('admin.extension.current_status')) . '：' . $status . '</p>'
            . '<form method="post" action="/admin/extensions/plugin/settings?plugin=' . rawurlencode($plugin) . '" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . $bindingBlock
            . $fields
            . '<div class="actions"><button type="submit">' . $this->escape($lang->get('admin.extension.save_config')) . '</button><a href="/admin/extensions">' . $this->escape($lang->get('admin.extension.back_extensions')) . '</a></div>'
            . '</form>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.extension.plugin_config'), $body));
    }

    public function savePluginSettings(): Response
    {
        $plugin = trim((string) $this->request->query('plugin', ''));
        $meta = $this->pluginMeta($plugin);
        $config = $this->pluginConfig($plugin);

        if ($plugin === '' || $meta === null || $config === null || !(bool) ($meta['preinstalled'] ?? false)) {
            return $this->redirect('/admin/extensions?plugin=0');
        }

        $bindResult = '';

        try {
            foreach (($config['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $type = (string) ($field['type'] ?? 'text');
                $storeType = (string) ($field['store_type'] ?? 'string');
                $autoload = (bool) ($field['autoload'] ?? true);
                $skipIfEmpty = (bool) ($field['skip_if_empty'] ?? false);

                if ($type === 'checkbox') {
                    $rawValue = $this->request->post($name) === '1';
                } else {
                    $raw = $this->request->post($name, '');
                    $rawValue = trim(is_scalar($raw) ? (string) $raw : '');
                    if ($skipIfEmpty && $rawValue === '') {
                        continue;
                    }
                }

                $value = $this->castFieldValue($rawValue, $storeType);

                PluginSetting::put($plugin, $name, $value, $storeType, $autoload);

                $systemKey = trim((string) ($field['sync_system_key'] ?? ''));
                if ($systemKey !== '') {
                    $this->settingService()->updateSystem([$systemKey => $value], [$systemKey]);
                }
            }

            $binding = $config['binding'] ?? null;
            if (is_array($binding)) {
                $bindAsActive = $this->request->post('bind_as_active') === '1';
                PluginSetting::put($plugin, 'bind_as_active', $bindAsActive, 'bool', true);

                if ($bindAsActive) {
                    $systemKey = trim((string) ($binding['system_key'] ?? ''));
                    if ($systemKey !== '') {
                        $enabled = (bool) ($meta['enabled'] ?? false);
                        if ($enabled) {
                            $this->settingService()->updateSystem([$systemKey => $plugin], [$systemKey]);
                        } else {
                            $bindResult = '&bind=0';
                        }
                    }
                }
            }

            $this->app->cache?->flushGroups(['plugin', 'settings', 'search', 'home', 'post', 'comment']);

            return $this->redirect('/admin/extensions/plugin/settings?plugin=' . rawurlencode($plugin) . '&saved=1' . $bindResult);
        } catch (Throwable) {
            return $this->redirect('/admin/extensions/plugin/settings?plugin=' . rawurlencode($plugin) . '&saved=0');
        }
    }

    private function content(): string
    {
        $themes = $this->themeService()->discover();
        $plugins = $this->pluginService()->discover();
        $modules = $this->moduleService()->discover();
        $token = $this->escape($this->app->session->csrfToken());

        $notice = '';
        if ($this->request->query('theme') === '1') {
            $notice .= '<div class="fp-notice-success">主题已切换。</div>';
        } elseif ($this->request->query('theme') === '0') {
            $notice .= '<div class="fp-error-text">主题切换失败。</div>';
        }

        if ($this->request->query('module') === '1') {
            $notice .= '<div class="fp-notice-success">模块状态已更新。</div>';
        } elseif ($this->request->query('module') === '0') {
            $notice .= '<div class="fp-error-text">模块状态更新失败。</div>';
        }

        if ($this->request->query('plugin') === '1') {
            $notice .= '<div class="fp-notice-success">插件状态已更新。</div>';
        } elseif ($this->request->query('plugin') === '0') {
            $notice .= '<div class="fp-error-text">插件状态更新失败。</div>';
        }

        $themeRows = '';
        foreach ($themes as $theme) {
            $id = (string) ($theme['id'] ?? '');
            $active = (bool) ($theme['active'] ?? false);
            $themeRows .= '<tr>'
                . '<td>' . $this->escape((string) ($theme['name'] ?? $id)) . '</td>'
                . '<td>' . $this->escape((string) ($theme['version'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($theme['author'] ?? '')) . '</td>'
                . '<td>' . ($active ? '<strong>已启用</strong>' : '未启用') . '</td>'
                . '<td>'
                . ($active ? '' : '<form method="post" action="/admin/extensions/theme" class="fp-inline-form">'
                    . '<input type="hidden" name="_token" value="' . $token . '">'
                    . '<input type="hidden" name="theme" value="' . $this->escape($id) . '">'
                    . '<button type="submit">启用</button>'
                    . '</form>')
                . '</td>'
                . '</tr>';
        }
        if ($themeRows === '') {
            $themeRows = '<tr><td colspan="5" class="muted">暂无主题元数据</td></tr>';
        }

        $pluginRows = '';
        foreach ($plugins as $plugin) {
            $id = (string) ($plugin['id'] ?? '');
            $enabled = (bool) ($plugin['enabled'] ?? false);
            $hasRuntime = (bool) ($plugin['has_runtime'] ?? false);
            $settingsPage = trim((string) ($plugin['settings_page'] ?? ''));
            if ($settingsPage === '' && $this->pluginConfig($id) !== null) {
                $settingsPage = '/admin/extensions/plugin/settings?plugin=' . rawurlencode($id);
            }

            $configAction = $settingsPage !== ''
                ? '<a href="' . $this->escape($settingsPage) . '">配置</a>'
                : '<span class="muted">-</span>';

            $pluginRows .= '<tr>'
                . '<td>' . $this->escape($id) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['version'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['menu_location'] ?? 'hidden')) . '</td>'
                . '<td>' . ((bool) ($plugin['preinstalled'] ?? false) ? '是' : '否') . '</td>'
                . '<td>' . ($hasRuntime ? '有' : '无') . '</td>'
                . '<td>' . ($enabled ? '<strong>启用</strong>' : '停用') . '</td>'
                . '<td>' . $configAction . '</td>'
                . '<td><form method="post" action="/admin/extensions/plugin" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="plugin" value="' . $this->escape($id) . '">'
                . '<input type="hidden" name="enabled" value="' . ($enabled ? '0' : '1') . '">'
                . '<button type="submit" class="secondary">' . ($enabled ? '停用' : '启用') . '</button>'
                . '</form></td>'
                . '</tr>';
        }
        if ($pluginRows === '') {
            $pluginRows = '<tr><td colspan="9" class="muted">暂无插件元数据</td></tr>';
        }

        $moduleRows = '';
        foreach ($modules as $module) {
            $id = (string) ($module['id'] ?? '');
            $enabled = (bool) ($module['enabled'] ?? true);
            $moduleRows .= '<tr>'
                . '<td>' . $this->escape($id) . '</td>'
                . '<td>' . $this->escape((string) ($module['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($module['version'] ?? '')) . '</td>'
                . '<td>' . ($enabled ? '<strong>启用</strong>' : '停用') . '</td>'
                . '<td><form method="post" action="/admin/extensions/module" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="module" value="' . $this->escape($id) . '">'
                . '<input type="hidden" name="enabled" value="' . ($enabled ? '0' : '1') . '">'
                . '<button type="submit" class="secondary">' . ($enabled ? '停用' : '启用') . '</button>'
                . '</form></td>'
                . '</tr>';
        }
        if ($moduleRows === '') {
            $moduleRows = '<tr><td colspan="5" class="muted">暂无模块元数据</td></tr>';
        }

        return '<section class="panel"><h1>扩展管理</h1>' . $notice . '<p class="muted">Stage18 基础骨架：插件/主题/模块元数据发现与最小操作。</p></section>'
            . '<section class="panel"><h2>主题</h2><table><thead><tr><th>名称</th><th>版本</th><th>作者</th><th>状态</th><th>操作</th></tr></thead><tbody>'
            . $themeRows
            . '</tbody></table></section>'
            . '<section class="panel"><h2>插件</h2><table><thead><tr><th>ID</th><th>名称</th><th>版本</th><th>菜单位置</th><th>预装</th><th>运行时</th><th>状态</th><th>配置</th><th>操作</th></tr></thead><tbody>'
            . $pluginRows
            . '</tbody></table></section>'
            . '<section class="panel"><h2>模块</h2><table><thead><tr><th>ID</th><th>名称</th><th>版本</th><th>状态</th><th>操作</th></tr></thead><tbody>'
            . $moduleRows
            . '</tbody></table></section>';
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $config
     * @param array<string,mixed> $values
     */
    private function pluginBindingBlock(string $plugin, array $meta, array $config, array $values): string
    {
        $binding = $config['binding'] ?? null;
        if (!is_array($binding)) {
            return '';
        }

        $systemKey = trim((string) ($binding['system_key'] ?? ''));
        if ($systemKey === '') {
            return '';
        }

        $label = (string) ($binding['label'] ?? '设为当前 Provider');
        $help = trim((string) ($binding['help'] ?? ''));
        $current = (string) $this->app->settings->get($systemKey, '');
        $bindValue = (bool) ($values['bind_as_active'] ?? false);
        $checked = $bindValue ? ' checked' : '';
        $enabled = (bool) ($meta['enabled'] ?? false);
        $enabledHint = $enabled
            ? ''
            : '<div class="fp-notice-warn fp-help-text">当前插件处于停用状态，勾选后不会切换系统默认 Provider。</div>';

        return '<label class="fp-check-row"><input type="checkbox" name="bind_as_active" value="1"' . $checked . '>'
            . $this->escape($label)
            . '</label>'
            . '<div class="muted">当前 system_setting.' . $this->escape($systemKey) . ' = <strong>' . $this->escape($current === '' ? '(空)' : $current) . '</strong></div>'
            . ($help !== '' ? '<div class="muted fp-help-text">' . $this->escape($help) . '</div>' : '')
            . $enabledHint;
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $values
     */
    private function pluginField(array $field, array $values): string
    {
        $name = trim((string) ($field['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        $label = (string) ($field['label'] ?? $name);
        $type = (string) ($field['type'] ?? 'text');
        $help = trim((string) ($field['help'] ?? ''));
        $value = $values[$name] ?? ($field['default'] ?? '');

        if ($type === 'checkbox') {
            $checked = (bool) $value ? ' checked' : '';

            return '<label class="fp-check-row"><input type="checkbox" name="' . $this->escape($name) . '" value="1"' . $checked . '>'
                . $this->escape($label)
                . '</label>'
                . ($help !== '' ? '<div class="muted fp-help-text">' . $this->escape($help) . '</div>' : '');
        }

        if ($type === 'select') {
            $options = $field['options'] ?? [];
            $optionHtml = '';
            if (is_array($options)) {
                foreach ($options as $optionValue => $optionLabel) {
                    $selected = (string) $value === (string) $optionValue ? ' selected' : '';
                    $optionHtml .= '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>' . $this->escape((string) $optionLabel) . '</option>';
                }
            }

            return '<label>' . $this->escape($label)
                . '<select name="' . $this->escape($name) . '">' . $optionHtml . '</select></label>'
                . ($help !== '' ? '<div class="muted fp-help-text">' . $this->escape($help) . '</div>' : '');
        }

        $inputType = in_array($type, ['text', 'password', 'number'], true) ? $type : 'text';
        $displayValue = $inputType === 'password' ? '' : (string) $value;

        return '<label>' . $this->escape($label)
            . '<input type="' . $this->escape($inputType) . '" name="' . $this->escape($name) . '" value="' . $this->escape($displayValue) . '"></label>'
            . ($help !== '' ? '<div class="muted fp-help-text">' . $this->escape($help) . '</div>' : '');
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function pluginSettingsValues(string $plugin, array $config): array
    {
        $values = [];

        foreach (($config['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $storeType = (string) ($field['store_type'] ?? 'string');
            $type = (string) ($field['type'] ?? 'text');
            $raw = PluginSetting::value($plugin, $name);

            if (($raw === null || ($raw === '' && $type !== 'text')) && isset($field['sync_system_key'])) {
                $syncKey = (string) $field['sync_system_key'];
                $raw = (string) $this->app->settings->get($syncKey, '');
            }

            if ($raw === null || $raw === '') {
                $values[$name] = $field['default'] ?? ($type === 'checkbox' ? false : '');
                continue;
            }

            $values[$name] = $this->castFieldValue($raw, $storeType);
        }

        $binding = $config['binding'] ?? null;
        if (is_array($binding)) {
            $systemKey = trim((string) ($binding['system_key'] ?? ''));
            $bindStored = PluginSetting::value($plugin, 'bind_as_active');

            if ($bindStored !== null) {
                $values['bind_as_active'] = in_array(strtolower(trim($bindStored)), ['1', 'true', 'yes', 'on'], true);
            } elseif ($systemKey !== '') {
                $values['bind_as_active'] = (string) $this->app->settings->get($systemKey, '') === $plugin;
            }
        }

        return $values;
    }

    private function castFieldValue(mixed $value, string $storeType): mixed
    {
        return match ($storeType) {
            'bool' => $value === true
                || $value === 1
                || $value === '1'
                || $value === 'true'
                || $value === 'on'
                || $value === 'yes',
            'int' => (int) $value,
            'float' => (float) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim((string) $value),
        };
    }

    /** @return array<string,mixed>|null */
    private function pluginConfig(string $plugin): ?array
    {
        return self::BUILTIN_PLUGIN_CONFIGS[$plugin] ?? null;
    }

    /** @return array<string,mixed>|null */
    private function pluginMeta(string $plugin): ?array
    {
        if ($plugin === '') {
            return null;
        }

        foreach ($this->pluginService()->discover() as $meta) {
            if ((string) ($meta['id'] ?? '') === $plugin) {
                return $meta;
            }
        }

        return null;
    }

    private function pluginService(): PluginService
    {
        return new PluginService();
    }

    private function settingService(): SettingService
    {
        return new SettingService($this->app->db, $this->app->settings);
    }

    private function themeService(): ThemeService
    {
        return new ThemeService($this->app->settings, FP_CONTENT_DIR . '/themes', $this->app->cache);
    }

    private function moduleService(): ModuleService
    {
        return new ModuleService(FP_CONTENT_DIR . '/modules', $this->app->cache);
    }
}
