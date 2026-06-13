<?php

/**
 * 模板标签函数（1.0 MVP）
 */

declare(strict_types=1);

use Finch\App;

if (!function_exists('fp_app')) {
    function fp_app(): App
    {
        return App::getInstance();
    }
}

if (!function_exists('fp_escape')) {
    function fp_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('site_name')) {
    function site_name(): string
    {
        return (string) fp_app()->settings->get('site_name', 'Finch');
    }
}

if (!function_exists('site_subtitle')) {
    function site_subtitle(): string
    {
        return (string) fp_app()->settings->get('site_subtitle', '');
    }
}

if (!function_exists('site_url')) {
    function site_url(): string
    {
        return rtrim((string) fp_app()->settings->get('site_url', ''), '/');
    }
}

if (!function_exists('active_view')) {
    function active_view(): string
    {
        return (string) fp_app()->context->get('view', '');
    }
}

if (!function_exists('current_post')) {
    /** @return array<string, mixed>|null */
    function current_post(): ?array
    {
        $value = fp_app()->context->current();

        return is_array($value) ? $value : null;
    }
}

if (!function_exists('query_data')) {
    /** @return array<string, mixed> */
    function query_data(): array
    {
        $query = fp_app()->context->get('query', []);

        return is_array($query) ? $query : [];
    }
}

if (!function_exists('title')) {
    function title(): string
    {
        $post = current_post();

        return $post === null ? site_name() : (string) ($post['title'] ?? site_name());
    }
}

if (!function_exists('content')) {
    function content(): string
    {
        $post = current_post();

        return (string) ($post['content'] ?? '');
    }
}

if (!function_exists('excerpt')) {
    function excerpt(): string
    {
        $post = current_post();

        return (string) ($post['excerpt'] ?? '');
    }
}

if (!function_exists('permalink')) {
    function permalink(?array $post = null): string
    {
        $post ??= current_post();
        if ($post === null) {
            return '/';
        }

        $type = (string) ($post['type'] ?? 'article');
        $slug = (string) ($post['slug'] ?? '');
        if ($type === 'page') {
            return '/page/' . rawurlencode($slug ?: (string) ($post['id'] ?? ''));
        }

        return '/post/' . rawurlencode($slug ?: (string) ($post['id'] ?? ''));
    }
}

if (!function_exists('post_date')) {
    function post_date(string $format = 'Y-m-d H:i', string $dateValue = ''): string
    {
        if ($dateValue !== '') {
            $value = $dateValue;
        } else {
            $post = current_post();
            $value = (string) ($post['published_at'] ?? $post['created_at'] ?? '');
        }
        if ($value === '') {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? $value : date($format, $time);
    }
}

if (!function_exists('author')) {
    function author(): string
    {
        $post = current_post();
        if ($post === null || !is_array($post['author'] ?? null)) {
            return '';
        }

        return (string) ($post['author']['display_name'] ?? $post['author']['username'] ?? '');
    }
}

if (!function_exists('categories')) {
    function categories(): string
    {
        $post = current_post();
        $items = is_array($post['categories'] ?? null) ? $post['categories'] : [];
        if ($items === []) {
            return '';
        }

        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = (string) ($item['slug'] ?? '');
            $name = (string) ($item['name'] ?? '');
            $links[] = '<a href="/category/' . rawurlencode($slug) . '">' . fp_escape($name) . '</a>';
        }

        return implode(', ', $links);
    }
}

if (!function_exists('tags')) {
    function tags(): string
    {
        $post = current_post();
        $items = is_array($post['tags'] ?? null) ? $post['tags'] : [];
        if ($items === []) {
            return '';
        }

        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = (string) ($item['slug'] ?? '');
            $name = (string) ($item['name'] ?? '');
            $links[] = '<a href="/tag/' . rawurlencode($slug) . '">#' . fp_escape($name) . '</a>';
        }

        return implode(' ', $links);
    }
}

if (!function_exists('list_posts')) {
    /** @return list<array<string, mixed>> */
    function list_posts(): array
    {
        $query = query_data();
        $items = $query['items'] ?? [];

        return is_array($items) ? $items : [];
    }
}

if (!function_exists('pagination')) {
    function pagination(): string
    {
        $query = query_data();
        $page = (int) ($query['page'] ?? 1);
        $lastPage = (int) ($query['last_page'] ?? 1);
        if ($lastPage <= 1) {
            return '';
        }

        $links = [];
        for ($i = 1; $i <= $lastPage; $i++) {
            if ($i === $page) {
                $links[] = '<strong>' . $i . '</strong>';
            } else {
                $links[] = '<a href="?page=' . $i . '">' . $i . '</a>';
            }
        }

        return '<nav class="pagination">' . implode(' ', $links) . '</nav>';
    }
}

if (!function_exists('head')) {
    function head(): string
    {
        fp_app()->hooks->do('fp_head');

        return '';
    }
}

if (!function_exists('footer')) {
    function footer(): string
    {
        fp_app()->hooks->do('fp_footer');

        return '';
    }
}

if (!function_exists('get_header')) {
    function get_header(): string
    {
        return fp_app()->template->render('header');
    }
}

if (!function_exists('get_footer')) {
    function get_footer(): string
    {
        return fp_app()->template->render('footer');
    }
}

if (!function_exists('get_sidebar')) {
    function get_sidebar(): string
    {
        return fp_app()->template->render('sidebar');
    }
}

if (!function_exists('sidebar_categories')) {
    /** @return list<array{id:int,name:string,slug:string,post_count:int}> */
    function sidebar_categories(int $limit = 10): array
    {
        $rows = fp_app()->db->table('category')
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'post_count' => (int) ($row['post_count'] ?? 0),
        ], $rows);
    }
}

if (!function_exists('nav_items')) {
    /**
     * 获取导航栏项目（由链接管理中心插件提供）。
     *
     * @param string $group 分组：navbar（默认）/ friend / custom
     * @return list<array{id:int,title:string,url:string,target:string,icon:string,type:string,ref_id:int,children?:list<array<mixed>>}>
     */
    function nav_items(string $group = 'navbar'): array
    {
        $items = fp_app()->hooks->filter('fp_nav_items', [], ['group' => $group]);

        return is_array($items) ? $items : [];
    }
}

if (!function_exists('friend_links')) {
    /**
     * 获取友情链接列表（由链接管理中心插件提供）。
     *
     * @return list<array{id:int,title:string,url:string,description:string,icon:string}>
     */
    function friend_links(): array
    {
        $items = fp_app()->hooks->filter('fp_friend_links', [], []);

        return is_array($items) ? $items : [];
    }
}
