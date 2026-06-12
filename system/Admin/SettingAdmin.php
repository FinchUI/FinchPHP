<?php

/**
 * Finch\Admin\SettingAdmin - 分组系统设置后台页
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\SettingService;

final class SettingAdmin extends BaseController
{
    use AdminLayout;

    /**
     * @var array<string, array{label:string,keys:list<string>,bools:list<string>,rules:array<string,string>}>
     */
    private const GROUPS = [
        'basic' => [
            'label' => '基本',
            'keys' => [
                'site_name',
                'site_subtitle',
                'site_url',
                'site_description',
                'site_keywords',
                'admin_language',
                'site_timezone',
                'maintenance_mode',
            ],
            'bools' => ['maintenance_mode'],
            'rules' => [
                'site_name' => 'required|max:100',
                'site_url' => 'max:255',
                'site_timezone' => 'required|max:64',
                'admin_language' => 'required|max:20',
            ],
        ],
        'reading' => [
            'label' => '阅读',
            'keys' => [
                'posts_per_page',
                'feed_items',
                'default_post_type',
            ],
            'bools' => [],
            'rules' => [
                'posts_per_page' => 'required|numeric|min:1|max:100',
                'feed_items' => 'required|numeric|min:1|max:100',
                'default_post_type' => 'required|max:50',
            ],
        ],
        'comments' => [
            'label' => '评论',
            'keys' => [
                'comment_enabled',
                'comment_need_audit',
                'comment_guest_allowed',
                'comment_login_required',
            ],
            'bools' => [
                'comment_enabled',
                'comment_need_audit',
                'comment_guest_allowed',
                'comment_login_required',
            ],
            'rules' => [],
        ],
        'permalinks' => [
            'label' => '固定链接',
            'keys' => [
                'permalink_structure',
                'category_base',
                'tag_base',
            ],
            'bools' => [],
            'rules' => [
                'permalink_structure' => 'required|max:120',
                'category_base' => 'required|max:60',
                'tag_base' => 'required|max:60',
            ],
        ],
        'api' => [
            'label' => 'API',
            'keys' => [
                'api_enabled',
                'api_cors_origins',
            ],
            'bools' => ['api_enabled'],
            'rules' => [
                'api_cors_origins' => 'max:1000',
            ],
        ],
        'mail' => [
            'label' => '邮件',
            'keys' => [
                'mail_provider',
                'mail_from_name',
                'mail_from_address',
                'smtp_host',
                'smtp_port',
                'smtp_user',
                'smtp_pass',
                'smtp_secure',
            ],
            'bools' => [],
            'rules' => [
                'mail_provider' => 'max:100',
                'mail_from_name' => 'max:100',
                'mail_from_address' => 'max:255',
                'smtp_host' => 'max:255',
                'smtp_port' => 'max:10',
                'smtp_user' => 'max:255',
                'smtp_pass' => 'max:255',
                'smtp_secure' => 'max:20',
            ],
        ],
    ];

    /** @var array<string, array{label:string,type:string,placeholder?:string,help?:string}> */
    private const FIELDS = [
        'site_name' => ['label' => '站点名称', 'type' => 'text'],
        'site_subtitle' => ['label' => '站点副标题', 'type' => 'text'],
        'site_url' => ['label' => '站点 URL', 'type' => 'text'],
        'site_description' => ['label' => '站点描述', 'type' => 'text'],
        'site_keywords' => ['label' => '关键词', 'type' => 'text'],
        'admin_language' => ['label' => '后台语言', 'type' => 'text'],
        'site_timezone' => ['label' => '业务时区', 'type' => 'text', 'placeholder' => 'Asia/Shanghai'],
        'maintenance_mode' => ['label' => '维护模式', 'type' => 'checkbox'],

        'posts_per_page' => ['label' => '每页文章数', 'type' => 'number'],
        'feed_items' => ['label' => 'Feed 条目数', 'type' => 'number'],
        'default_post_type' => ['label' => '默认文章类型', 'type' => 'text'],

        'comment_enabled' => ['label' => '启用评论', 'type' => 'checkbox'],
        'comment_need_audit' => ['label' => '新评论需要审核', 'type' => 'checkbox'],
        'comment_guest_allowed' => ['label' => '允许游客评论', 'type' => 'checkbox'],
        'comment_login_required' => ['label' => '评论必须登录', 'type' => 'checkbox'],

        'permalink_structure' => ['label' => '文章固定链接', 'type' => 'text', 'placeholder' => '/post/{id}'],
        'category_base' => ['label' => '分类前缀', 'type' => 'text'],
        'tag_base' => ['label' => '标签前缀', 'type' => 'text'],

        'api_enabled' => ['label' => '启用 API', 'type' => 'checkbox'],
        'api_cors_origins' => ['label' => '允许跨域来源', 'type' => 'text', 'help' => '多个来源请用逗号分隔。'],

        'mail_provider' => ['label' => '邮件 Provider', 'type' => 'text'],
        'mail_from_name' => ['label' => '发件人名称', 'type' => 'text'],
        'mail_from_address' => ['label' => '发件人地址', 'type' => 'text'],
        'smtp_host' => ['label' => 'SMTP Host', 'type' => 'text'],
        'smtp_port' => ['label' => 'SMTP Port', 'type' => 'text'],
        'smtp_user' => ['label' => 'SMTP 用户名', 'type' => 'text'],
        'smtp_pass' => ['label' => 'SMTP 密码', 'type' => 'password', 'placeholder' => '留空表示不修改'],
        'smtp_secure' => ['label' => 'SMTP 加密方式', 'type' => 'text', 'placeholder' => 'tls/ssl'],
    ];

    public function index(): Response
    {
        $group = $this->normalizeGroup((string) $this->request->query('group', 'basic'));
        $config = self::GROUPS[$group];
        $settings = $this->settingService()->getMany($config['keys']);

        return $this->html($this->adminShell('系统设置', $this->content($group, $settings)));
    }

    public function save(): Response
    {
        $group = $this->normalizeGroup((string) $this->request->query('group', 'basic'));
        $config = self::GROUPS[$group];

        $validator = $this->validate($config['rules']);
        if ($validator->fails()) {
            $settings = array_replace(
                $this->settingService()->getMany($config['keys']),
                $this->request->all(),
            );

            return $this->html($this->adminShell('系统设置', $this->content($group, $settings, $validator->errors())), 422);
        }

        $input = $this->request->all();
        foreach ($config['bools'] as $boolKey) {
            $input[$boolKey] = $this->request->post($boolKey) === '1';
        }

        if ($group === 'mail' && trim((string) ($input['smtp_pass'] ?? '')) === '') {
            unset($input['smtp_pass']);
        }

        if ($group === 'basic') {
            $timezone = (string) ($input['site_timezone'] ?? '');
            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                $settings = array_replace(
                    $this->settingService()->getMany($config['keys']),
                    $this->request->all(),
                );

                return $this->html($this->adminShell('系统设置', $this->content($group, $settings, [
                    'site_timezone' => ['时区不存在。'],
                ])), 422);
            }
        }

        $this->settingService()->updateSystem($input, $config['keys']);

        return $this->redirect('/admin/settings?group=' . rawurlencode($group) . '&saved=1');
    }

    private function settingService(): SettingService
    {
        return new SettingService($this->app->db, $this->app->settings);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, list<string>> $errors
     */
    private function content(string $group, array $settings, array $errors = []): string
    {
        $config = self::GROUPS[$group];
        $saved = $this->request->query('saved') === '1'
            ? '<div style="color:#067647">设置已保存。</div>'
            : '';

        $fields = '';
        foreach ($config['keys'] as $key) {
            $fields .= $this->field($key, $settings, $errors);
        }

        return '<section class="panel"><h1>系统设置</h1>'
            . $saved
            . $this->tabs($group)
            . '<form method="post" action="/admin/settings?group=' . $this->escape($group) . '" style="display:grid;gap:12px;max-width:760px">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . $fields
            . '<button type="submit" style="width:max-content">保存设置</button>'
            . '</form>'
            . '</section>';
    }

    private function tabs(string $group): string
    {
        $parts = ['<div class="actions" style="margin-bottom:12px">'];
        foreach (self::GROUPS as $name => $item) {
            $url = '/admin/settings?group=' . rawurlencode($name);
            $style = $name === $group ? ' style="font-weight:600"' : '';
            $parts[] = '<a href="' . $this->escape($url) . '"' . $style . '>' . $this->escape($item['label']) . '</a>';
        }
        $parts[] = '</div>';

        return implode('', $parts);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, list<string>> $errors
     */
    private function field(string $key, array $settings, array $errors): string
    {
        $meta = self::FIELDS[$key] ?? ['label' => $key, 'type' => 'text'];
        $label = (string) $meta['label'];
        $type = (string) $meta['type'];
        $help = isset($meta['help']) ? '<div class="muted" style="margin-top:4px">' . $this->escape((string) $meta['help']) . '</div>' : '';
        $error = isset($errors[$key][0])
            ? '<div style="color:#b42318;margin-top:4px">' . $this->escape($errors[$key][0]) . '</div>'
            : '';

        if ($type === 'checkbox') {
            $checked = (bool) ($settings[$key] ?? false) ? 'checked' : '';

            return '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="' . $this->escape($key) . '" value="1" ' . $checked . '>'
                . $this->escape($label)
                . '</label>'
                . $help
                . $error;
        }

        $placeholder = isset($meta['placeholder']) ? ' placeholder="' . $this->escape((string) $meta['placeholder']) . '"' : '';
        $value = $type === 'password' ? '' : $this->escape((string) ($settings[$key] ?? ''));

        return '<label>' . $this->escape($label)
            . '<input type="' . $this->escape($type) . '" name="' . $this->escape($key) . '" value="' . $value . '"' . $placeholder . '></label>'
            . $help
            . $error;
    }

    private function normalizeGroup(string $group): string
    {
        return array_key_exists($group, self::GROUPS) ? $group : 'basic';
    }
}
