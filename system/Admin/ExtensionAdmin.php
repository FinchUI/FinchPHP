<?php

/**
 * Finch\Admin\ExtensionAdmin - 扩展基础管理页
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\ModuleService;
use Finch\Service\PluginService;
use Finch\Service\ThemeService;

final class ExtensionAdmin extends BaseController
{
    use AdminLayout;

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
            $pluginRows .= '<tr>'
                . '<td>' . $this->escape($id) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['version'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($plugin['menu_location'] ?? 'hidden')) . '</td>'
                . '<td>' . ((bool) ($plugin['preinstalled'] ?? false) ? '是' : '否') . '</td>'
                . '<td>' . ($hasRuntime ? '有' : '无') . '</td>'
                . '<td>' . ($enabled ? '<strong>启用</strong>' : '停用') . '</td>'
                . '<td><form method="post" action="/admin/extensions/plugin" style="display:inline">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="plugin" value="' . $this->escape($id) . '">'
                . '<input type="hidden" name="enabled" value="' . ($enabled ? '0' : '1') . '">'
                . '<button type="submit" class="secondary">' . ($enabled ? '停用' : '启用') . '</button>'
                . '</form></td>'
                . '</tr>';
        }
        if ($pluginRows === '') {
            $pluginRows = '<tr><td colspan="8" class="muted">暂无插件元数据</td></tr>';
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
            . '<section class="panel"><h2>插件</h2><table><thead><tr><th>ID</th><th>名称</th><th>版本</th><th>菜单位置</th><th>预装</th><th>运行时</th><th>状态</th><th>操作</th></tr></thead><tbody>'
            . $pluginRows
            . '</tbody></table></section>'
            . '<section class="panel"><h2>模块</h2><table><thead><tr><th>ID</th><th>名称</th><th>版本</th><th>状态</th><th>操作</th></tr></thead><tbody>'
            . $moduleRows
            . '</tbody></table></section>';
    }

    private function pluginService(): PluginService
    {
        return new PluginService();
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
