<?php

/**
 * Finch\Admin\ModuleAdmin - 模块管理后台页
 *
 * 侧栏模块的增删改与排序（见 PROJECT_PLAN §4.13 第 8 项）。
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\Module;
use Finch\Service\ModuleService;

final class ModuleAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $sidebar = trim((string) $this->request->query('sidebar', 'side1'));
        $modules = $this->moduleService()->listBySidebar($sidebar);
        $sidebars = ['side1' => $lang->get('admin.module.sidebar_side1'), 'side2' => $lang->get('admin.module.sidebar_side2')];
        $token = $this->escape($this->app->session->csrfToken());

        $notice = $this->noticeHtml();

        $sidebarTabs = '';
        foreach ($sidebars as $key => $label) {
            $active = $key === $sidebar ? ' fp-tab-active' : '';
            $sidebarTabs .= '<a class="fp-tab-link' . $active . '" href="/admin/modules?sidebar=' . $this->escape($key) . '">' . $this->escape($label) . '</a>';
        }

        $rows = '';
        foreach ($modules as $module) {
            $id = (int) ($module['id'] ?? 0);
            $name = (string) ($module['name'] ?? '');
            $type = (string) ($module['type'] ?? 'custom');
            $active = (bool) ($module['is_active'] ?? false);
            $position = (int) ($module['position'] ?? 0);

            $rows .= '<tr>'
                . '<td>' . $this->escape($name) . '</td>'
                . '<td>' . $this->escape($type) . '</td>'
                . '<td>' . $position . '</td>'
                . '<td>' . ($active ? '<strong>' . $this->escape($lang->get('admin.common.enabled')) . '</strong>' : $this->escape($lang->get('admin.common.disable'))) . '</td>'
                . '<td>'
                . '<form method="post" action="/admin/modules/toggle" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="is_active" value="' . ($active ? '0' : '1') . '">'
                . '<button type="submit" class="secondary">' . ($active ? $this->escape($lang->get('admin.common.disable')) : $this->escape($lang->get('admin.common.enable'))) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted">' . $this->escape($lang->get('admin.module.no_modules')) . '</td></tr>';
        }

        $body = '<section class="panel"><h1>' . $this->escape($lang->get('admin.module.title')) . '</h1>'
            . $notice
            . '<div class="actions fp-actions-gap-bottom">' . $sidebarTabs . '</div>'
            . '<table><thead><tr>'
            . '<th>' . $this->escape($lang->get('admin.module.th_name')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.module.th_type')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.module.th_position')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.status')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.actions')) . '</th>'
            . '</tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.module.title'), $body));
    }

    public function toggle(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $isActive = $this->request->post('is_active', '1') === '1';
        $sidebar = trim((string) $this->request->post('sidebar', 'side1'));

        if ($id > 0) {
            $this->app->db->table('module')->where('id', $id)->update([
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        return $this->redirect('/admin/modules?sidebar=' . rawurlencode($sidebar) . '&saved=1');
    }

    public function saveOrder(): Response
    {
        $orders = $this->request->post('order', []);
        $sidebar = trim((string) $this->request->post('sidebar', 'side1'));

        if (is_array($orders)) {
            foreach ($orders as $position => $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $this->app->db->table('module')->where('id', $id)->update([
                        'position' => (int) $position,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        return $this->redirect('/admin/modules?sidebar=' . rawurlencode($sidebar) . '&saved=1');
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
            return '<div class="fp-notice-success">' . $this->escape($lang->get('admin.module.saved')) . '</div>';
        }

        return '';
    }
}
