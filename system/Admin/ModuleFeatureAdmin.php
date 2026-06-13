<?php

/**
 * Finch\Admin\ModuleFeatureAdmin - 功能模块管理
 *
 * 官方模块启用/禁用管理（见 PROJECT_PLAN §4.13 第 13 项）。
 * 与 ModuleAdmin（侧栏模块）不同，此页面管理的是 content/modules/ 下的功能模块。
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\ModuleService;

final class ModuleFeatureAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $modules = $this->moduleService()->discover();
        $token = $this->escape($this->app->session->csrfToken());

        $notice = $this->noticeHtml();

        $rows = '';
        foreach ($modules as $module) {
            $id = (string) ($module['id'] ?? '');
            $name = (string) ($module['name'] ?? $id);
            $version = (string) ($module['version'] ?? '');
            $description = (string) ($module['description'] ?? '');
            $enabled = (bool) ($module['enabled'] ?? true);

            $rows .= '<tr>'
                . '<td>' . $this->escape($id) . '</td>'
                . '<td>' . $this->escape($name) . '</td>'
                . '<td>' . $this->escape($version) . '</td>'
                . '<td>' . $this->escape($description) . '</td>'
                . '<td>' . ($enabled ? '<strong>' . $this->escape($lang->get('admin.common.enabled')) . '</strong>' : $this->escape($lang->get('admin.common.disable'))) . '</td>'
                . '<td><form method="post" action="/admin/module-features/toggle" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="module" value="' . $this->escape($id) . '">'
                . '<input type="hidden" name="enabled" value="' . ($enabled ? '0' : '1') . '">'
                . '<button type="submit" class="secondary">' . ($enabled ? $this->escape($lang->get('admin.common.disable')) : $this->escape($lang->get('admin.common.enable'))) . '</button>'
                . '</form></td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted">' . $this->escape($lang->get('admin.module_feature.no_modules')) . '</td></tr>';
        }

        $body = '<section class="panel"><h1>' . $this->escape($lang->get('admin.module_feature.title')) . '</h1>'
            . $notice
            . '<p class="muted">' . $this->escape($lang->get('admin.module_feature.description')) . '</p>'
            . '<table><thead><tr>'
            . '<th>ID</th>'
            . '<th>' . $this->escape($lang->get('admin.module_feature.th_name')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.module_feature.th_version')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.module_feature.th_description')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.status')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.actions')) . '</th>'
            . '</tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.module_feature.title'), $body));
    }

    public function toggle(): Response
    {
        $module = trim((string) $this->request->post('module', ''));
        $enabled = (string) $this->request->post('enabled', '1') === '1';
        $ok = $this->moduleService()->setEnabled($module, $enabled);

        return $this->redirect('/admin/module-features?saved=' . ($ok ? '1' : '0'));
    }

    private function moduleService(): ModuleService
    {
        return new ModuleService(FP_CONTENT_DIR . '/modules', $this->app->cache);
    }

    private function noticeHtml(): string
    {
        $lang = $this->app->lang;
        $saved = (string) $this->request->query('saved', '');

        if ($saved === '1') {
            return '<div class="fp-notice-success">' . $this->escape($lang->get('admin.module_feature.saved')) . '</div>';
        }
        if ($saved === '0') {
            return '<div class="fp-error-text">' . $this->escape($lang->get('admin.module_feature.save_failed')) . '</div>';
        }

        return '';
    }
}
