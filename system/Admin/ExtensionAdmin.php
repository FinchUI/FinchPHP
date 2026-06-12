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
            'title' => 'Tabler 后台样式',
            'description' => '配置后台样式插件的基础行为。',
            'binding' => [
                'system_key' => 'admin_style',
                'label' => '设为当前后台样式提供者',
                'help' => '勾选后保存将切换 system_setting.admin_style 为 tabler。',
            ],
            'fields' => [
                [
                    'name' => 'brand_title',
                    'label' => '后台品牌标题',
                    'type' => 'text',
                    'default' => 'Finch Admin',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'sidebar_compact',
                    'label' => '紧凑侧栏',
                    'type' => 'checkbox',
                    'default' => false,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'quill' => [
            'title' => 'Quill 编辑器',
            'description' => '配置编辑器插件基础参数。',
            'binding' => [
                'system_key' => 'editor',
                'label' => '设为当前编辑器',
                'help' => '勾选后保存将切换 system_setting.editor 为 quill。',
            ],
            'fields' => [
                [
                    'name' => 'toolbar_mode',
                    'label' => '工具栏模式',
                    'type' => 'select',
                    'options' => [
                        'simple' => '简洁',
                        'full' => '完整',
                    ],
                    'default' => 'simple',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'placeholder',
                    'label' => '编辑器占位文本',
                    'type' => 'text',
                    'default' => '开始编写内容...',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
            ],
        ],
        'search-like' => [
            'title' => 'Search LIKE 搜索',
            'description' => '配置 SQL LIKE 搜索 Provider 的行为。',
            'binding' => [
                'system_key' => 'search_provider',
                'label' => '设为当前搜索 Provider',
                'help' => '勾选后保存将切换 system_setting.search_provider 为 search-like。',
            ],
            'fields' => [
                [
                    'name' => 'match_title',
                    'label' => '匹配标题',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'match_excerpt',
                    'label' => '匹配摘要',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'match_content',
                    'label' => '匹配正文',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
                [
                    'name' => 'per_page_limit',
                    'label' => '单页结果上限',
                    'type' => 'number',
                    'default' => 50,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
            ],
        ],
        'notify-email' => [
            'title' => 'Notify Email 邮件通道',
            'description' => '配置邮件 Provider 与 SMTP 参数。',
            'binding' => [
                'system_key' => 'mail_provider',
                'label' => '设为当前邮件 Provider',
                'help' => '勾选后保存将切换 system_setting.mail_provider 为 notify-email。',
            ],
            'fields' => [
                [
                    'name' => 'mail_from_name',
                    'label' => '发件人名称',
                    'type' => 'text',
                    'default' => 'Finch',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'mail_from_name',
                ],
                [
                    'name' => 'mail_from_address',
                    'label' => '发件人地址',
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
                    'label' => 'SMTP 用户名',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'sync_system_key' => 'smtp_user',
                ],
                [
                    'name' => 'smtp_pass',
                    'label' => 'SMTP 密码',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => '留空表示不修改已保存密码。',
                    'sync_system_key' => 'smtp_pass',
                ],
                [
                    'name' => 'smtp_secure',
                    'label' => 'SMTP 加密方式',
                    'type' => 'select',
                    'options' => [
                        '' => '无',
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
            'title' => 'Notify SMS 短信通道',
            'description' => '配置短信 Provider 的通道参数。',
            'binding' => [
                'system_key' => 'sms_provider',
                'label' => '设为当前短信 Provider',
                'help' => '勾选后保存将切换 system_setting.sms_provider 为 notify-sms。',
            ],
            'fields' => [
                [
                    'name' => 'vendor',
                    'label' => '服务商标识',
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
                    'help' => '留空表示不修改已保存 Key。',
                ],
                [
                    'name' => 'api_secret',
                    'label' => 'API Secret',
                    'type' => 'password',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                    'skip_if_empty' => true,
                    'help' => '留空表示不修改已保存 Secret。',
                ],
                [
                    'name' => 'sign_name',
                    'label' => '签名',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
                [
                    'name' => 'template_code',
                    'label' => '模板编号',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => false,
                ],
            ],
        ],
        'captcha-gd' => [
            'title' => 'Captcha GD 验证码',
            'description' => '配置图形验证码基础参数。',
            'binding' => [
                'system_key' => 'captcha_provider',
                'label' => '设为当前验证码 Provider',
                'help' => '勾选后保存将切换 system_setting.captcha_provider 为 captcha-gd。',
            ],
            'fields' => [
                [
                    'name' => 'code_length',
                    'label' => '验证码长度',
                    'type' => 'number',
                    'default' => 4,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
                [
                    'name' => 'ttl_seconds',
                    'label' => '验证码有效期（秒）',
                    'type' => 'number',
                    'default' => 300,
                    'store_type' => 'int',
                    'autoload' => true,
                ],
                [
                    'name' => 'case_sensitive',
                    'label' => '区分大小写',
                    'type' => 'checkbox',
                    'default' => false,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'social-github' => [
            'title' => 'Social GitHub 登录',
            'description' => '配置 GitHub OAuth 登录参数。',
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
                    'help' => '留空表示不修改已保存 Secret。',
                ],
                [
                    'name' => 'redirect_uri',
                    'label' => '回调地址',
                    'type' => 'text',
                    'default' => '',
                    'store_type' => 'string',
                    'autoload' => true,
                ],
                [
                    'name' => 'auto_create_user',
                    'label' => '自动创建本地账号',
                    'type' => 'checkbox',
                    'default' => true,
                    'store_type' => 'bool',
                    'autoload' => true,
                ],
            ],
        ],
        'ai-openai' => [
            'title' => 'AI OpenAI 协议',
            'description' => '配置 OpenAI 协议 Provider 的请求参数。',
            'binding' => [
                'system_key' => 'ai_provider',
                'label' => '设为当前 AI Provider',
                'help' => '勾选后保存将切换 system_setting.ai_provider 为 ai-openai。',
            ],
            'fields' => [
                [
                    'name' => 'base_url',
                    'label' => '接口地址',
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
                    'help' => '留空表示不修改已保存 Key。',
                ],
                [
                    'name' => 'model',
                    'label' => '默认模型',
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
                    'label' => '超时秒数',
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
        return $this->html($this->adminShell('扩展管理', $this->content()));
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
        $plugin = trim((string) $this->request->query('plugin', ''));
        $meta = $this->pluginMeta($plugin);
        $config = $this->pluginConfig($plugin);

        if ($plugin === '' || $meta === null || $config === null || !(bool) ($meta['preinstalled'] ?? false)) {
            return $this->html($this->adminShell('插件配置', '<section class="panel"><h1>插件配置</h1><div style="color:#b42318">未找到可配置的内置插件。</div><p><a href="/admin/extensions">返回扩展管理</a></p></section>'), 404);
        }

        $values = $this->pluginSettingsValues($plugin, $config);
        $saved = (string) $this->request->query('saved', '');
        $bind = (string) $this->request->query('bind', '');
        $notice = '';
        if ($saved === '1') {
            $notice .= '<div style="color:#067647">插件配置已保存。</div>';
        } elseif ($saved === '0') {
            $notice .= '<div style="color:#b42318">插件配置保存失败，请检查输入后重试。</div>';
        }

        if ($bind === '0') {
            $notice .= '<div style="color:#b54708">插件当前为停用状态，未切换为系统默认 Provider。</div>';
        }

        $status = (bool) ($meta['enabled'] ?? false) ? '<strong>启用</strong>' : '停用';
        $fields = '';
        foreach (($config['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fields .= $this->pluginField($field, $values);
        }

        $bindingBlock = $this->pluginBindingBlock($plugin, $meta, $config, $values);
        $token = $this->escape($this->app->session->csrfToken());

        $body = '<section class="panel"><h1>' . $this->escape((string) ($config['title'] ?? $plugin)) . '</h1>'
            . $notice
            . '<p class="muted">' . $this->escape((string) ($config['description'] ?? '')) . '</p>'
            . '<p>插件 ID：<strong>' . $this->escape($plugin) . '</strong>；当前状态：' . $status . '</p>'
            . '<form method="post" action="/admin/extensions/plugin/settings?plugin=' . rawurlencode($plugin) . '" style="display:grid;gap:12px;max-width:760px">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . $bindingBlock
            . $fields
            . '<div class="actions"><button type="submit">保存配置</button><a href="/admin/extensions">返回扩展管理</a></div>'
            . '</form>'
            . '</section>';

        return $this->html($this->adminShell('插件配置', $body));
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
            $notice .= '<div style="color:#067647">主题已切换。</div>';
        } elseif ($this->request->query('theme') === '0') {
            $notice .= '<div style="color:#b42318">主题切换失败。</div>';
        }

        if ($this->request->query('module') === '1') {
            $notice .= '<div style="color:#067647">模块状态已更新。</div>';
        } elseif ($this->request->query('module') === '0') {
            $notice .= '<div style="color:#b42318">模块状态更新失败。</div>';
        }

        if ($this->request->query('plugin') === '1') {
            $notice .= '<div style="color:#067647">插件状态已更新。</div>';
        } elseif ($this->request->query('plugin') === '0') {
            $notice .= '<div style="color:#b42318">插件状态更新失败。</div>';
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
                . ($active ? '' : '<form method="post" action="/admin/extensions/theme" style="display:inline">'
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
                . '<td><form method="post" action="/admin/extensions/plugin" style="display:inline">'
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
                . '<td><form method="post" action="/admin/extensions/module" style="display:inline">'
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
            : '<div style="color:#b54708;margin-top:4px">当前插件处于停用状态，勾选后不会切换系统默认 Provider。</div>';

        return '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="bind_as_active" value="1"' . $checked . '>'
            . $this->escape($label)
            . '</label>'
            . '<div class="muted">当前 system_setting.' . $this->escape($systemKey) . ' = <strong>' . $this->escape($current === '' ? '(空)' : $current) . '</strong></div>'
            . ($help !== '' ? '<div class="muted" style="margin-top:4px">' . $this->escape($help) . '</div>' : '')
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

            return '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="' . $this->escape($name) . '" value="1"' . $checked . '>'
                . $this->escape($label)
                . '</label>'
                . ($help !== '' ? '<div class="muted" style="margin-top:4px">' . $this->escape($help) . '</div>' : '');
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
                . ($help !== '' ? '<div class="muted" style="margin-top:4px">' . $this->escape($help) . '</div>' : '');
        }

        $inputType = in_array($type, ['text', 'password', 'number'], true) ? $type : 'text';
        $displayValue = $inputType === 'password' ? '' : (string) $value;

        return '<label>' . $this->escape($label)
            . '<input type="' . $this->escape($inputType) . '" name="' . $this->escape($name) . '" value="' . $this->escape($displayValue) . '"></label>'
            . ($help !== '' ? '<div class="muted" style="margin-top:4px">' . $this->escape($help) . '</div>' : '');
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
