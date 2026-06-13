<?php

declare(strict_types=1);

/**
 * 开发模式插件
 *
 * 在后台仪表盘注入开发工具面板：
 *   - 一键重装网站（清空数据库 + 重新迁移 + 种子数据）
 *   - 一键导入演示数据（分类/文章/标签/评论）
 *   - 一键清空演示数据
 *
 * 仅供开发阶段使用，打包时排除此插件目录即可。
 */

return static function (\Finch\App $app, array $meta = []): void {
    // 在仪表盘页面注入开发工具面板
    $app->hooks->add('fp_dashboard_after_shortcuts', static function (mixed $value) use ($app): string {
        $existing = is_string($value) ? $value : '';
        $lang = $app->lang;
        $token = htmlspecialchars($app->session->csrfToken(), ENT_QUOTES, 'UTF-8');

        return $existing . '<section class="panel fp-dev-panel">'
            . '<h2 style="color:#dc2626;">' . htmlspecialchars($lang->get('dev.title'), ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p class="muted">' . htmlspecialchars($lang->get('dev.description'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="fp-dev-actions">'
            . '<form method="post" action="/admin/dev-mode/reinstall" class="fp-inline-form">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<button type="submit" class="fp-btn-danger" onclick="return confirm(\'' . htmlspecialchars($lang->get('dev.confirm_reinstall'), ENT_QUOTES, 'UTF-8') . '\')">' . htmlspecialchars($lang->get('dev.reinstall'), ENT_QUOTES, 'UTF-8') . '</button>'
            . '</form>'
            . '<form method="post" action="/admin/dev-mode/seed" class="fp-inline-form">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<button type="submit" onclick="return confirm(\'' . htmlspecialchars($lang->get('dev.confirm_seed'), ENT_QUOTES, 'UTF-8') . '\')">' . htmlspecialchars($lang->get('dev.seed'), ENT_QUOTES, 'UTF-8') . '</button>'
            . '</form>'
            . '<form method="post" action="/admin/dev-mode/clean" class="fp-inline-form">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<button type="submit" class="secondary" onclick="return confirm(\'' . htmlspecialchars($lang->get('dev.confirm_clean'), ENT_QUOTES, 'UTF-8') . '\')">' . htmlspecialchars($lang->get('dev.clean'), ENT_QUOTES, 'UTF-8') . '</button>'
            . '</form>'
            . '</div>'
            . '</section>';
    });
};
