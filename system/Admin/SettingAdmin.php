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
            'label' => 'admin.settings.group_basic',
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
            'label' => 'admin.settings.group_reading',
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
            'label' => 'admin.settings.group_comments',
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
            'label' => 'admin.settings.group_permalinks',
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
            'label' => 'admin.settings.group_api',
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
            'label' => 'admin.settings.group_mail',
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

    /** @var array<string, array{label:string,type:string,placeholder?:string,help?:string,options?:array<string,string>}> */
    private const FIELDS = [
        'site_name' => ['label' => 'admin.settings.site_name', 'type' => 'text'],
        'site_subtitle' => ['label' => 'admin.settings.site_subtitle', 'type' => 'text'],
        'site_url' => ['label' => 'admin.settings.site_url', 'type' => 'text'],
        'site_description' => ['label' => 'admin.settings.site_description', 'type' => 'text'],
        'site_keywords' => ['label' => 'admin.settings.site_keywords', 'type' => 'text'],
        'admin_language' => ['label' => 'admin.settings.admin_language', 'type' => 'select', 'options' => ['zh-cn' => '简体中文', 'zh-tw' => '繁體中文', 'en' => 'English']],
        'site_timezone' => ['label' => 'admin.settings.site_timezone', 'type' => 'timezone'],
        'maintenance_mode' => ['label' => 'admin.settings.maintenance_mode', 'type' => 'checkbox'],

        'posts_per_page' => ['label' => 'admin.settings.posts_per_page', 'type' => 'number'],
        'feed_items' => ['label' => 'admin.settings.feed_items', 'type' => 'number'],
        'default_post_type' => ['label' => 'admin.settings.default_post_type', 'type' => 'text'],

        'comment_enabled' => ['label' => 'admin.settings.comment_enabled', 'type' => 'checkbox'],
        'comment_need_audit' => ['label' => 'admin.settings.comment_need_audit', 'type' => 'checkbox'],
        'comment_guest_allowed' => ['label' => 'admin.settings.comment_guest_allowed', 'type' => 'checkbox'],
        'comment_login_required' => ['label' => 'admin.settings.comment_login_required', 'type' => 'checkbox'],

        'permalink_structure' => ['label' => 'admin.settings.permalink_structure', 'type' => 'text', 'placeholder' => '/post/{id}'],
        'category_base' => ['label' => 'admin.settings.category_base', 'type' => 'text'],
        'tag_base' => ['label' => 'admin.settings.tag_base', 'type' => 'text'],

        'api_enabled' => ['label' => 'admin.settings.api_enabled', 'type' => 'checkbox'],
        'api_cors_origins' => ['label' => 'admin.settings.api_cors_origins', 'type' => 'text', 'help' => 'admin.settings.api_cors_help'],

        'mail_provider' => ['label' => 'admin.settings.mail_provider', 'type' => 'text'],
        'mail_from_name' => ['label' => 'admin.settings.mail_from_name', 'type' => 'text'],
        'mail_from_address' => ['label' => 'admin.settings.mail_from_address', 'type' => 'text'],
        'smtp_host' => ['label' => 'admin.settings.smtp_host', 'type' => 'text'],
        'smtp_port' => ['label' => 'admin.settings.smtp_port', 'type' => 'text'],
        'smtp_user' => ['label' => 'admin.settings.smtp_user', 'type' => 'text'],
        'smtp_pass' => ['label' => 'admin.settings.smtp_pass', 'type' => 'password', 'placeholder' => 'admin.settings.smtp_pass_placeholder'],
        'smtp_secure' => ['label' => 'admin.settings.smtp_secure', 'type' => 'text', 'placeholder' => 'tls/ssl'],
    ];

    public function index(): Response
    {
        $group = $this->normalizeGroup((string) $this->request->query('group', 'basic'));
        $config = self::GROUPS[$group];
        $settings = $this->settingService()->getMany($config['keys']);

        return $this->html($this->adminShell($this->app->lang->get('admin.settings.title'), $this->content($group, $settings)));
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

            return $this->html($this->adminShell($this->app->lang->get('admin.settings.title'), $this->content($group, $settings, $validator->errors())), 422);
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

                return $this->html($this->adminShell($this->app->lang->get('admin.settings.title'), $this->content($group, $settings, [
                    'site_timezone' => [$this->app->lang->get('admin.message.timezone_invalid')],
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
        $lang = $this->app->lang;
        $config = self::GROUPS[$group];
        $saved = $this->request->query('saved') === '1'
            ? '<div class="fp-notice-success">' . $this->escape($lang->get('admin.message.settings_saved')) . '</div>'
            : '';

        $fields = '';
        foreach ($config['keys'] as $key) {
            $fields .= $this->field($key, $settings, $errors);
        }

        return '<section class="panel"><h1>' . $this->escape($lang->get('admin.settings.title')) . '</h1>'
            . $saved
            . $this->tabs($group)
            . '<form method="post" action="/admin/settings?group=' . $this->escape($group) . '" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $this->escape($this->app->session->csrfToken()) . '">'
            . $fields
            . '<button type="submit" class="fp-btn-wide">' . $this->escape($lang->get('admin.settings.save')) . '</button>'
            . '</form>'
            . '</section>';
    }

    private function tabs(string $group): string
    {
        $lang = $this->app->lang;
        $parts = ['<div class="actions fp-actions-gap-bottom">'];
        foreach (self::GROUPS as $name => $item) {
            $url = '/admin/settings?group=' . rawurlencode($name);
            $active = $name === $group ? ' fp-tab-active' : '';
            $parts[] = '<a class="fp-tab-link' . $active . '" href="' . $this->escape($url) . '">' . $this->escape($lang->get($item['label'])) . '</a>';
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
        $lang = $this->app->lang;
        $meta = self::FIELDS[$key] ?? ['label' => $key, 'type' => 'text'];
        $label = $lang->get((string) $meta['label']);
        $type = (string) $meta['type'];
        $help = isset($meta['help']) ? '<div class="muted fp-help-text">' . $this->escape($lang->get((string) $meta['help'])) . '</div>' : '';
        $error = isset($errors[$key][0])
            ? '<div class="fp-error-text">' . $this->escape($errors[$key][0]) . '</div>'
            : '';

        if ($type === 'checkbox') {
            $checked = (bool) ($settings[$key] ?? false) ? 'checked' : '';

            return '<label class="fp-check-row"><input type="checkbox" name="' . $this->escape($key) . '" value="1" ' . $checked . '>'
                . $this->escape($label)
                . '</label>'
                . $help
                . $error;
        }

        if ($type === 'select') {
            $options = $meta['options'] ?? [];
            $optionHtml = '';
            $currentValue = (string) ($settings[$key] ?? '');
            foreach ($options as $optionValue => $optionLabel) {
                $selected = $currentValue === (string) $optionValue ? ' selected' : '';
                $optionHtml .= '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>' . $this->escape((string) $optionLabel) . '</option>';
            }

            return '<label>' . $this->escape($label)
                . '<select name="' . $this->escape($key) . '">' . $optionHtml . '</select></label>'
                . $help
                . $error;
        }

        if ($type === 'timezone') {
            return '<label>' . $this->escape($label)
                . '<select name="' . $this->escape($key) . '">' . $this->timezoneOptions((string) ($settings[$key] ?? '')) . '</select></label>'
                . $help
                . $error;
        }

        $placeholder = isset($meta['placeholder']) ? ' placeholder="' . $this->escape($lang->get((string) $meta['placeholder'])) . '"' : '';
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

    /**
     * 生成时区 <option> 列表，参考 install/index.php 的 fp_install_timezone_options()
     */
    private function timezoneOptions(string $current): string
    {
        $html = '';
        foreach (timezone_identifiers_list() as $tz) {
            $selected = $tz === $current ? ' selected' : '';
            $label = str_replace('_', ' ', $tz);
            $html .= '<option value="' . $this->escape($tz) . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }
        return $html;
    }
}
