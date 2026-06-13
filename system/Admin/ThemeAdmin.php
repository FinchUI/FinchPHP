<?php

/**
 * Finch\Admin\ThemeAdmin - 主题管理独立页面
 *
 * 主题切换、预览、自定义（见 PROJECT_PLAN §4.13 第 12 项）。
 * 从 ExtensionAdmin 中独立出来，提供更丰富的主题管理功能。
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\ThemeService;

final class ThemeAdmin extends BaseController
{
    use AdminLayout;

    public function index(): Response
    {
        $lang = $this->app->lang;
        $themes = $this->themeService()->discover();
        $currentTheme = (string) $this->app->settings->get('active_theme', 'default');
        $token = $this->escape($this->app->session->csrfToken());

        $notice = $this->noticeHtml();

        $cards = '';
        foreach ($themes as $theme) {
            $id = (string) ($theme['id'] ?? '');
            $active = $id === $currentTheme;
            $name = (string) ($theme['name'] ?? $id);
            $version = (string) ($theme['version'] ?? '');
            $author = (string) ($theme['author'] ?? '');
            $description = (string) ($theme['description'] ?? '');
            $screenshot = (string) ($theme['screenshot'] ?? '');

            $screenshotHtml = $screenshot !== ''
                ? '<div class="fp-theme-screenshot"><img src="' . $this->escape($screenshot) . '" alt="' . $this->escape($name) . '"></div>'
                : '<div class="fp-theme-screenshot fp-theme-no-screenshot"><span>' . $this->escape($lang->get('admin.theme.no_screenshot')) . '</span></div>';

            $actionHtml = $active
                ? '<span class="fp-badge-active">' . $this->escape($lang->get('admin.theme.current')) . '</span>'
                : '<form method="post" action="/admin/themes/activate">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="theme" value="' . $this->escape($id) . '">'
                . '<button type="submit">' . $this->escape($lang->get('admin.theme.activate')) . '</button>'
                . '</form>';

            $cards .= '<div class="fp-theme-card' . ($active ? ' fp-theme-active' : '') . '">'
                . $screenshotHtml
                . '<div class="fp-theme-info">'
                . '<h3>' . $this->escape($name) . ' <small>v' . $this->escape($version) . '</small></h3>'
                . ($author !== '' ? '<p class="muted">' . $this->escape($author) . '</p>' : '')
                . ($description !== '' ? '<p>' . $this->escape($description) . '</p>' : '')
                . '<div class="actions">' . $actionHtml . '</div>'
                . '</div>'
                . '</div>';
        }

        if ($cards === '') {
            $cards = '<div class="muted">' . $this->escape($lang->get('admin.theme.no_themes')) . '</div>';
        }

        $body = '<section class="panel"><h1>' . $this->escape($lang->get('admin.theme.title')) . '</h1>'
            . $notice
            . '<div class="fp-theme-grid">'
            . $cards
            . '</div>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.theme.title'), $body));
    }

    public function activate(): Response
    {
        $theme = trim((string) $this->request->post('theme', ''));
        $ok = $this->themeService()->activate($theme);

        return $this->redirect('/admin/themes?saved=' . ($ok ? '1' : '0'));
    }

    public function preview(): Response
    {
        $theme = trim((string) $this->request->query('theme', ''));
        if ($theme === '') {
            return $this->redirect('/admin/themes');
        }

        // 预览模式：设置 cookie，前台检测后使用预览主题（仅管理员生效）
        setcookie('fp_preview_theme', $theme, [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);

        return $this->redirect('/admin/themes?preview=' . $this->escape($theme));
    }

    private function themeService(): ThemeService
    {
        return new ThemeService($this->app->settings, FP_CONTENT_DIR . '/themes', $this->app->cache);
    }

    private function noticeHtml(): string
    {
        $lang = $this->app->lang;
        $saved = (string) $this->request->query('saved', '');
        $preview = (string) $this->request->query('preview', '');

        $notice = '';
        if ($saved === '1') {
            $notice .= '<div class="fp-notice-success">' . $this->escape($lang->get('admin.theme.activated')) . '</div>';
        } elseif ($saved === '0') {
            $notice .= '<div class="fp-error-text">' . $this->escape($lang->get('admin.theme.activate_failed')) . '</div>';
        }

        if ($preview !== '') {
            $notice .= '<div class="fp-notice-success">' . $this->escape($lang->get('admin.theme.preview_on')) . ' <a href="/" target="_blank">' . $this->escape($lang->get('admin.theme.view_site')) . '</a></div>';
        }

        return $notice;
    }
}
