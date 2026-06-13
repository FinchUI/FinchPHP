<?php

/**
 * Finch\Admin\LinkAdmin - 链接管理中心后台
 *
 * 管理导航栏（一级/二级菜单、拖拽排序）、友情链接、自定义链接。
 */

declare(strict_types=1);

namespace Finch\Admin;

use Finch\Controller\BaseController;
use Finch\Core\Response;

final class LinkAdmin extends BaseController
{
    use AdminLayout;

    // ========== 导航栏管理 ==========

    public function index(): Response
    {
        $lang = $this->app->lang;
        $group = trim((string) $this->request->query('group', 'navbar'));
        if (!in_array($group, ['navbar', 'friend', 'custom'], true)) {
            $group = 'navbar';
        }

        $links = $this->getTree($group);
        $token = $this->escape($this->app->session->csrfToken());
        $saved = $this->savedNotice();

        $groupTabs = $this->groupTabs($group);
        $rows = $this->renderTree($links, $group, $token);

        $addUrl = '/admin/links/create?group=' . rawurlencode($group);
        $searchPanel = $group === 'navbar' ? $this->searchPanel($token) : '';

        $body = '<section class="panel"><h1>' . $this->escape($lang->get('admin.link.title')) . '</h1>'
            . $saved
            . '<div class="actions fp-actions-gap-bottom">' . $groupTabs . '</div>'
            . '</section>'
            . $searchPanel
            . '<section class="panel">'
            . '<div class="actions"><a href="' . $this->escape($addUrl) . '" class="button">' . $this->escape($lang->get('admin.link.add')) . '</a></div>'
            . '<form method="post" action="/admin/links/sort" class="fp-link-sort-form" id="fp-link-sort-form">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<input type="hidden" name="group" value="' . $this->escape($group) . '">'
            . '<input type="hidden" name="order" id="fp-link-order" value="">'
            . '<table id="fp-link-table"><thead><tr>'
            . '<th>' . $this->escape($lang->get('admin.link.th_title')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.link.th_url')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.link.th_type')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.link.th_target')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.link.th_status')) . '</th>'
            . '<th>' . $this->escape($lang->get('admin.common.actions')) . '</th>'
            . '</tr></thead><tbody>'
            . ($rows === '' ? '<tr><td colspan="6" class="muted">' . $this->escape($lang->get('admin.link.empty')) . '</td></tr>' : $rows)
            . '</tbody></table>'
            . '<div class="actions"><button type="submit" class="secondary">' . $this->escape($lang->get('admin.link.save_order')) . '</button></div>'
            . '</form>'
            . '</section>';

        return $this->html($this->adminShell($lang->get('admin.link.title'), $body));
    }

    public function create(): Response
    {
        $lang = $this->app->lang;
        $group = trim((string) $this->request->query('group', 'navbar'));
        $linkType = trim((string) $this->request->query('type', 'custom'));
        $refId = (int) $this->request->query('ref_id', 0);

        $defaults = [
            'group_key' => $group,
            'title' => '',
            'url' => '',
            'link_type' => $linkType,
            'ref_id' => $refId,
            'parent_id' => 0,
            'target' => '_self',
            'icon' => '',
            'description' => '',
            'is_active' => 1,
        ];

        // 如果是关联类型，自动填充标题和 URL
        if ($refId > 0 && in_array($linkType, ['page', 'category', 'tag', 'post'], true)) {
            $defaults = array_merge($defaults, $this->resolveRef($linkType, $refId));
        }

        return $this->html($this->adminShell(
            $lang->get('admin.link.create'),
            $this->form('/admin/links/store', $defaults),
        ));
    }

    public function store(): Response
    {
        $lang = $this->app->lang;
        $data = $this->request->all();
        $group = trim((string) ($data['group_key'] ?? 'navbar'));

        $title = trim((string) ($data['title'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($title === '') {
            return $this->redirect('/admin/links/create?group=' . rawurlencode($group) . '&saved=0');
        }

        $linkType = trim((string) ($data['link_type'] ?? 'custom'));
        if (!in_array($linkType, ['custom', 'page', 'category', 'tag', 'post'], true)) {
            $linkType = 'custom';
        }

        $parentId = (int) ($data['parent_id'] ?? 0);
        $maxSort = (int) $this->app->db->table('link')
            ->where('group_key', $group)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        $this->app->db->table('link')->insert([
            'group_key'  => $group,
            'parent_id'  => $parentId > 0 ? $parentId : null,
            'title'      => $title,
            'url'        => $url,
            'link_type'  => $linkType,
            'ref_id'     => (int) ($data['ref_id'] ?? 0) ?: null,
            'target'     => in_array(($data['target'] ?? ''), ['_self', '_blank'], true) ? $data['target'] : '_self',
            'icon'       => trim((string) ($data['icon'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'sort_order' => $maxSort + 1,
            'is_active'  => (int) ($data['is_active'] ?? 1),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->redirect('/admin/links?group=' . rawurlencode($group) . '&saved=1');
    }

    public function edit(): Response
    {
        $lang = $this->app->lang;
        $id = (int) $this->request->query('id', 0);
        $link = $this->app->db->table('link')->where('id', $id)->whereNull('deleted_at')->first();

        if ($link === null) {
            return $this->html($this->adminShell($lang->get('admin.link.title'),
                '<section class="panel"><h1>' . $this->escape($lang->get('admin.link.not_found')) . '</h1></section>'), 404);
        }

        $data = [
            'id'         => (int) $link['id'],
            'group_key'  => (string) $link['group_key'],
            'parent_id'  => (int) ($link['parent_id'] ?? 0),
            'title'      => (string) $link['title'],
            'url'        => (string) $link['url'],
            'link_type'  => (string) $link['link_type'],
            'ref_id'     => (int) ($link['ref_id'] ?? 0),
            'target'     => (string) ($link['target'] ?? '_self'),
            'icon'       => (string) ($link['icon'] ?? ''),
            'description' => (string) ($link['description'] ?? ''),
            'is_active'  => (int) $link['is_active'],
        ];

        return $this->html($this->adminShell(
            $lang->get('admin.link.edit'),
            $this->form('/admin/links/update', $data, (int) $link['id']),
        ));
    }

    public function update(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $link = $this->app->db->table('link')->where('id', $id)->whereNull('deleted_at')->first();
        if ($link === null) {
            return $this->redirect('/admin/links?saved=0');
        }

        $data = $this->request->all();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return $this->redirect('/admin/links/edit?id=' . $id . '&saved=0');
        }

        $linkType = trim((string) ($data['link_type'] ?? 'custom'));
        if (!in_array($linkType, ['custom', 'page', 'category', 'tag', 'post'], true)) {
            $linkType = 'custom';
        }

        $parentId = (int) ($data['parent_id'] ?? 0);

        $this->app->db->table('link')->where('id', $id)->update([
            'parent_id'  => $parentId > 0 ? $parentId : null,
            'title'      => $title,
            'url'        => trim((string) ($data['url'] ?? '')),
            'link_type'  => $linkType,
            'ref_id'     => (int) ($data['ref_id'] ?? 0) ?: null,
            'target'     => in_array(($data['target'] ?? ''), ['_self', '_blank'], true) ? $data['target'] : '_self',
            'icon'       => trim((string) ($data['icon'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'is_active'  => (int) ($data['is_active'] ?? 1),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $group = trim((string) ($data['group_key'] ?? 'navbar'));

        return $this->redirect('/admin/links?group=' . rawurlencode($group) . '&saved=1');
    }

    public function delete(): Response
    {
        $id = (int) $this->request->post('id', 0);
        $group = trim((string) $this->request->post('group_key', 'navbar'));

        if ($id > 0) {
            // 软删除自身及子项
            $this->app->db->table('link')->where('id', $id)->update([
                'deleted_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->app->db->table('link')->where('parent_id', $id)->update([
                'deleted_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        return $this->redirect('/admin/links?group=' . rawurlencode($group) . '&saved=1');
    }

    public function sort(): Response
    {
        $group = trim((string) $this->request->post('group', 'navbar'));
        $orderJson = trim((string) $this->request->post('order', ''));

        if ($orderJson !== '') {
            $order = json_decode($orderJson, true);
            if (is_array($order)) {
                $this->saveSortOrder($order);
            }
        }

        return $this->redirect('/admin/links?group=' . rawurlencode($group) . '&saved=1');
    }

    /** 搜索已有的页面/分类/标签/文章（AJAX） */
    public function search(): Response
    {
        $type = trim((string) $this->request->query('type', 'page'));
        $q = trim((string) $this->request->query('q', ''));
        $limit = min(20, max(1, (int) $this->request->query('limit', 10)));

        $results = match ($type) {
            'page'     => $this->searchPages($q, $limit),
            'category' => $this->searchCategories($q, $limit),
            'tag'      => $this->searchTags($q, $limit),
            'post'     => $this->searchPosts($q, $limit),
            default    => [],
        };

        return (new Response())->json($results);
    }

    // ========== 表单 ==========

    /**
     * @param array<string, mixed> $data
     */
    private function form(string $action, array $data, int $id = 0): string
    {
        $lang = $this->app->lang;
        $token = $this->escape($this->app->session->csrfToken());
        $group = (string) ($data['group_key'] ?? 'navbar');

        $parentId = (int) ($data['parent_id'] ?? 0);
        $parentOptions = $this->parentOptions($group, $id);

        $linkType = (string) ($data['link_type'] ?? 'custom');
        $typeOptions = '';
        foreach (['custom' => $lang->get('admin.link.type_custom'), 'page' => $lang->get('admin.link.type_page'), 'category' => $lang->get('admin.link.type_category'), 'tag' => $lang->get('admin.link.type_tag'), 'post' => $lang->get('admin.link.type_post')] as $val => $label) {
            $selected = $linkType === $val ? ' selected' : '';
            $typeOptions .= '<option value="' . $this->escape($val) . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }

        return '<section class="panel"><h1>' . ($id > 0 ? $this->escape($lang->get('admin.link.edit')) : $this->escape($lang->get('admin.link.create'))) . '</h1>'
            . '<form method="post" action="' . $this->escape($action) . '" class="fp-form-grid">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . ($id > 0 ? '<input type="hidden" name="id" value="' . $id . '">' : '')
            . '<input type="hidden" name="group_key" value="' . $this->escape($group) . '">'
            . '<label>' . $this->escape($lang->get('admin.link.th_title')) . '<input name="title" value="' . $this->escape((string) ($data['title'] ?? '')) . '" required></label>'
            . '<label>' . $this->escape($lang->get('admin.link.th_url')) . '<input name="url" value="' . $this->escape((string) ($data['url'] ?? '')) . '" placeholder="https://"></label>'
            . '<label>' . $this->escape($lang->get('admin.link.th_type')) . '<select name="link_type">' . $typeOptions . '</select></label>'
            . '<input type="hidden" name="ref_id" value="' . (int) ($data['ref_id'] ?? 0) . '">'
            . '<label>' . $this->escape($lang->get('admin.link.parent')) . '<select name="parent_id">'
            . '<option value="0">' . $this->escape($lang->get('admin.link.no_parent')) . '</option>'
            . $parentOptions
            . '</select></label>'
            . '<label>' . $this->escape($lang->get('admin.link.th_target')) . '<select name="target">'
            . '<option value="_self"' . (($data['target'] ?? '_self') === '_self' ? ' selected' : '') . '>' . $this->escape($lang->get('admin.link.target_self')) . '</option>'
            . '<option value="_blank"' . (($data['target'] ?? '') === '_blank' ? ' selected' : '') . '>' . $this->escape($lang->get('admin.link.target_blank')) . '</option>'
            . '</select></label>'
            . '<label>' . $this->escape($lang->get('admin.link.icon')) . '<input name="icon" value="' . $this->escape((string) ($data['icon'] ?? '')) . '" placeholder="ti ti-home"></label>'
            . '<label>' . $this->escape($lang->get('admin.link.description')) . '<input name="description" value="' . $this->escape((string) ($data['description'] ?? '')) . '"></label>'
            . '<label class="fp-check-row"><input type="checkbox" name="is_active" value="1"' . ((int) ($data['is_active'] ?? 1) === 1 ? ' checked' : '') . '>' . $this->escape($lang->get('admin.link.active')) . '</label>'
            . '<div class="actions"><button type="submit">' . $this->escape($lang->get('admin.common.save')) . '</button><a href="/admin/links?group=' . rawurlencode($group) . '">' . $this->escape($lang->get('admin.common.back_list')) . '</a></div>'
            . '</form></section>';
    }

    // ========== 搜索 ==========

    /** @return list<array{id:int,title:string,url:string}> */
    private function searchPages(string $q, int $limit): array
    {
        $query = $this->app->db->table('post')
            ->where('type', 'page')
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->limit($limit);

        if ($q !== '') {
            $query->where('title', 'LIKE', "%{$q}%");
        }

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'title' => (string) $row['title'],
            'url'   => '/page/' . rawurlencode((string) $row['slug']),
        ], $query->orderBy('id', 'DESC')->get());
    }

    /** @return list<array{id:int,title:string,url:string}> */
    private function searchCategories(string $q, int $limit): array
    {
        $query = $this->app->db->table('category')
            ->where('status', 'active')
            ->limit($limit);

        if ($q !== '') {
            $query->where('name', 'LIKE', "%{$q}%");
        }

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'title' => (string) $row['name'],
            'url'   => '/category/' . rawurlencode((string) $row['slug']),
        ], $query->orderBy('id', 'DESC')->get());
    }

    /** @return list<array{id:int,title:string,url:string}> */
    private function searchTags(string $q, int $limit): array
    {
        $query = $this->app->db->table('tag')->limit($limit);

        if ($q !== '') {
            $query->where('name', 'LIKE', "%{$q}%");
        }

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'title' => (string) $row['name'],
            'url'   => '/tag/' . rawurlencode((string) $row['slug']),
        ], $query->orderBy('id', 'DESC')->get());
    }

    /** @return list<array{id:int,title:string,url:string}> */
    private function searchPosts(string $q, int $limit): array
    {
        $query = $this->app->db->table('post')
            ->where('type', 'article')
            ->where('status', 'publish')
            ->whereNull('deleted_at')
            ->limit($limit);

        if ($q !== '') {
            $query->where('title', 'LIKE', "%{$q}%");
        }

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'title' => (string) $row['title'],
            'url'   => '/post/' . rawurlencode((string) $row['slug']),
        ], $query->orderBy('id', 'DESC')->get());
    }

    /** 关联类型自动填充 */
    private function resolveRef(string $linkType, int $refId): array
    {
        $row = match ($linkType) {
            'page'     => $this->app->db->table('post')->where('id', $refId)->where('type', 'page')->first(),
            'category' => $this->app->db->table('category')->where('id', $refId)->first(),
            'tag'      => $this->app->db->table('tag')->where('id', $refId)->first(),
            'post'     => $this->app->db->table('post')->where('id', $refId)->where('type', 'article')->first(),
            default    => null,
        };

        if ($row === null) {
            return [];
        }

        $title = (string) ($row['title'] ?? $row['name'] ?? '');
        $slug = (string) ($row['slug'] ?? '');

        $url = match ($linkType) {
            'page'     => '/page/' . rawurlencode($slug),
            'category' => '/category/' . rawurlencode($slug),
            'tag'      => '/tag/' . rawurlencode($slug),
            'post'     => '/post/' . rawurlencode($slug),
            default    => '',
        };

        return ['title' => $title, 'url' => $url, 'ref_id' => $refId];
    }

    // ========== 渲染辅助 ==========

    /** @return list<array<string, mixed>> */
    private function getTree(string $group): array
    {
        $rows = $this->app->db->table('link')
            ->where('group_key', $group)
            ->whereNull('deleted_at')
            ->orderBy('parent_id', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        $parents = [];
        $children = [];

        foreach ($rows as $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0) {
                $children[$parentId][] = $row;
            } else {
                $parents[] = $row;
            }
        }

        $tree = [];
        foreach ($parents as $parent) {
            $pid = (int) $parent['id'];
            $parent['_children'] = $children[$pid] ?? [];
            $tree[] = $parent;
        }

        return $tree;
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function renderTree(array $tree, string $group, string $token): string
    {
        $lang = $this->app->lang;
        $html = '';

        foreach ($tree as $item) {
            $id = (int) $item['id'];
            $title = (string) $item['title'];
            $url = (string) $item['url'];
            $linkType = (string) $item['link_type'];
            $target = (string) ($item['target'] ?? '_self');
            $isActive = (bool) $item['is_active'];
            $children = $item['_children'] ?? [];

            $typeLabels = [
                'custom'   => $lang->get('admin.link.type_custom'),
                'page'     => $lang->get('admin.link.type_page'),
                'category' => $lang->get('admin.link.type_category'),
                'tag'      => $lang->get('admin.link.type_tag'),
                'post'     => $lang->get('admin.link.type_post'),
            ];

            $indent = '';
            $rowClass = 'fp-link-row';
            $handle = '<span class="fp-link-handle" draggable="true" data-id="' . $id . '">⠿</span>';

            $html .= '<tr class="' . $rowClass . '" data-id="' . $id . '">'
                . '<td>' . $handle . ' ' . $this->escape($title) . '</td>'
                . '<td class="muted">' . $this->escape(mb_strlen($url) > 50 ? mb_substr($url, 0, 50) . '…' : $url) . '</td>'
                . '<td>' . $this->escape($typeLabels[$linkType] ?? $linkType) . '</td>'
                . '<td>' . ($target === '_blank' ? '_blank' : '_self') . '</td>'
                . '<td>' . ($isActive ? '<strong>' . $this->escape($lang->get('admin.common.enabled')) . '</strong>' : $this->escape($lang->get('admin.common.disable'))) . '</td>'
                . '<td class="actions">'
                . '<a href="/admin/links/edit?id=' . $id . '&group=' . rawurlencode($group) . '">' . $this->escape($lang->get('admin.common.edit')) . '</a>'
                . '<form method="post" action="/admin/links/delete" class="fp-inline-form">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="group_key" value="' . $this->escape($group) . '">'
                . '<button type="submit" class="secondary" onclick="return confirm(\'' . $this->escape($lang->get('admin.link.confirm_delete')) . '\')">' . $this->escape($lang->get('admin.common.delete')) . '</button>'
                . '</form>'
                . '</td>'
                . '</tr>';

            // 子项缩进
            foreach ($children as $child) {
                $cid = (int) $child['id'];
                $cTitle = (string) $child['title'];
                $cUrl = (string) $child['url'];
                $cLinkType = (string) $child['link_type'];
                $cTarget = (string) ($child['target'] ?? '_self');
                $cIsActive = (bool) $child['is_active'];
                $cHandle = '<span class="fp-link-handle" draggable="true" data-id="' . $cid . '" data-parent="' . $id . '">⠿</span>';

                $html .= '<tr class="fp-link-row fp-link-child" data-id="' . $cid . '" data-parent="' . $id . '">'
                    . '<td style="padding-left:2rem">' . $cHandle . ' ' . $this->escape($cTitle) . '</td>'
                    . '<td class="muted">' . $this->escape(mb_strlen($cUrl) > 50 ? mb_substr($cUrl, 0, 50) . '…' : $cUrl) . '</td>'
                    . '<td>' . $this->escape($typeLabels[$cLinkType] ?? $cLinkType) . '</td>'
                    . '<td>' . ($cTarget === '_blank' ? '_blank' : '_self') . '</td>'
                    . '<td>' . ($cIsActive ? '<strong>' . $this->escape($lang->get('admin.common.enabled')) . '</strong>' : $this->escape($lang->get('admin.common.disable'))) . '</td>'
                    . '<td class="actions">'
                    . '<a href="/admin/links/edit?id=' . $cid . '&group=' . rawurlencode($group) . '">' . $this->escape($lang->get('admin.common.edit')) . '</a>'
                    . '<form method="post" action="/admin/links/delete" class="fp-inline-form">'
                    . '<input type="hidden" name="_token" value="' . $token . '">'
                    . '<input type="hidden" name="id" value="' . $cid . '">'
                    . '<input type="hidden" name="group_key" value="' . $this->escape($group) . '">'
                    . '<button type="submit" class="secondary" onclick="return confirm(\'' . $this->escape($lang->get('admin.link.confirm_delete')) . '\')">' . $this->escape($lang->get('admin.common.delete')) . '</button>'
                    . '</form>'
                    . '</td>'
                    . '</tr>';
            }
        }

        return $html;
    }

    private function groupTabs(string $active): string
    {
        $lang = $this->app->lang;
        $tabs = [
            'navbar'  => $lang->get('admin.link.group_navbar'),
            'friend' => $lang->get('admin.link.group_friend'),
            'custom' => $lang->get('admin.link.group_custom'),
        ];

        $html = '';
        foreach ($tabs as $key => $label) {
            $cls = $key === $active ? ' fp-tab-active' : '';
            $html .= '<a class="fp-tab-link' . $cls . '" href="/admin/links?group=' . $this->escape($key) . '">' . $this->escape($label) . '</a>';
        }

        return $html;
    }

    private function parentOptions(string $group, int $excludeId = 0): string
    {
        $rows = $this->app->db->table('link')
            ->where('group_key', $group)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'ASC')
            ->get();

        $html = '';
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($id === $excludeId) {
                continue;
            }
            $html .= '<option value="' . $id . '">' . $this->escape((string) $row['title']) . '</option>';
        }

        return $html;
    }

    private function searchPanel(string $token): string
    {
        $lang = $this->app->lang;

        return '<section class="panel fp-link-search-panel">'
            . '<h2>' . $this->escape($lang->get('admin.link.quick_add')) . '</h2>'
            . '<div class="fp-link-search-bar">'
            . '<select id="fp-link-search-type">'
            . '<option value="page">' . $this->escape($lang->get('admin.link.type_page')) . '</option>'
            . '<option value="category">' . $this->escape($lang->get('admin.link.type_category')) . '</option>'
            . '<option value="tag">' . $this->escape($lang->get('admin.link.type_tag')) . '</option>'
            . '<option value="post">' . $this->escape($lang->get('admin.link.type_post')) . '</option>'
            . '</select>'
            . '<input type="text" id="fp-link-search-q" placeholder="' . $this->escape($lang->get('admin.link.search_placeholder')) . '">'
            . '<button type="button" id="fp-link-search-btn" class="secondary">' . $this->escape($lang->get('admin.link.search')) . '</button>'
            . '</div>'
            . '<div id="fp-link-search-results" class="fp-link-search-results"></div>'
            . '</section>';
    }

    /**
     * @param list<array{id:int,children?:list<array{id:int>}>} $order
     */
    private function saveSortOrder(array $order): void
    {
        $now = gmdate('Y-m-d H:i:s');

        foreach ($order as $position => $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $this->app->db->table('link')->where('id', $id)->update([
                'parent_id'  => null,
                'sort_order' => $position,
                'updated_at' => $now,
            ]);

            $children = $item['children'] ?? [];
            foreach ($children as $childPos => $child) {
                $childId = (int) ($child['id'] ?? 0);
                if ($childId <= 0) {
                    continue;
                }

                $this->app->db->table('link')->where('id', $childId)->update([
                    'parent_id'  => $id,
                    'sort_order' => $childPos,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function savedNotice(): string
    {
        $lang = $this->app->lang;
        $saved = (string) $this->request->query('saved', '');

        if ($saved === '1') {
            return '<div class="fp-notice-success">' . $this->escape($lang->get('admin.link.saved')) . '</div>';
        }
        if ($saved === '0') {
            return '<div class="fp-error-text">' . $this->escape($lang->get('admin.link.save_failed')) . '</div>';
        }

        return '';
    }
}
