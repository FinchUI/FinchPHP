<?php

declare(strict_types=1);

/**
 * 链接管理中心插件
 *
 * 管理导航栏（支持二级菜单）、友情链接、自定义链接。
 * 通过 fp_nav_items 钩子向模板提供导航数据。
 */

return static function (\Finch\App $app, array $meta = []): void {
    // 注册导航数据钩子 —— 主题模板通过 fp_app()->hooks->filter('fp_nav_items', []) 获取导航
    $app->hooks->add('fp_nav_items', static function (mixed $value, array $payload = []) use ($app): mixed {
        if ($value !== null && is_array($value)) {
            return $value;
        }

        $group = (string) ($payload['group'] ?? 'navbar');
        $db = $app->db;

        $rows = $db->table('link')
            ->where('group_key', $group)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('parent_id', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $items = [];
        $children = [];

        foreach ($rows as $row) {
            $item = [
                'id'    => (int) $row['id'],
                'title' => (string) $row['title'],
                'url'   => (string) $row['url'],
                'target' => (string) ($row['target'] ?? '_self'),
                'icon'  => (string) ($row['icon'] ?? ''),
                'type'  => (string) $row['link_type'],
                'ref_id' => (int) ($row['ref_id'] ?? 0),
            ];
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0) {
                $children[$parentId][] = $item;
            } else {
                $items[$item['id']] = $item;
            }
        }

        // 挂载子菜单
        foreach ($children as $pid => $subs) {
            if (isset($items[$pid])) {
                $items[$pid]['children'] = $subs;
            }
        }

        return array_values($items);
    });

    // 注册友情链接钩子
    $app->hooks->add('fp_friend_links', static function (mixed $value, array $payload = []) use ($app): mixed {
        if ($value !== null && is_array($value)) {
            return $value;
        }

        $db = $app->db;
        $rows = $db->table('link')
            ->where('group_key', 'friend')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'title' => (string) $row['title'],
            'url'   => (string) $row['url'],
            'description' => (string) ($row['description'] ?? ''),
            'icon'  => (string) ($row['icon'] ?? ''),
        ], $rows);
    });

    // 注册友情链接渲染钩子（供侧栏模板使用）
    $app->hooks->add('fp_render_friend_links', static function (mixed $value, array $payload = []) use ($app): mixed {
        if ($value !== null && is_string($value)) {
            return $value;
        }

        $links = $app->hooks->filter('fp_friend_links', [], $payload);
        if ($links === []) {
            return '';
        }

        $html = '<div class="friend-links">';
        foreach ($links as $link) {
            $html .= '<a href="' . htmlspecialchars((string) $link['url'], ENT_QUOTES) . '"'
                . ' title="' . htmlspecialchars((string) $link['title'], ENT_QUOTES) . '"'
                . ' target="_blank" rel="noopener">'
                . htmlspecialchars((string) $link['title'], ENT_QUOTES)
                . '</a>';
        }
        $html .= '</div>';

        return $html;
    });
};
