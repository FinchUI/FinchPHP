<?php

declare(strict_types=1);

return static function (\Finch\App $app, array $meta = []): void {
    $app->hooks->add('fp_provider_search_search_like_query', static function (mixed $value, array $payload = []) use ($app): mixed {
        if ($value !== null) {
            return $value;
        }

        $keyword = trim((string) ($payload['keyword'] ?? ''));
        $page = max(1, (int) ($payload['page'] ?? 1));
        $perPage = max(1, (int) ($payload['per_page'] ?? 10));

        if ($keyword === '') {
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 0,
                'provider' => 'search-like',
            ];
        }

        $prefix = $app->db->prefix();
        $like = '%' . $keyword . '%';
        $now = gmdate('Y-m-d H:i:s');

        $countRow = $app->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)',
            ['publish', 'article', $now, $like, $like, $like],
        );

        $total = (int) ($countRow['aggregate'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $rows = $app->db->select(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.type, p.status, p.published_at, p.created_at, p.updated_at "
            . "FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) '
            . 'ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            ['publish', 'article', $now, $like, $like, $like, $perPage, $offset],
        );

        $data = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'type' => (string) ($row['type'] ?? 'article'),
                'status' => (string) ($row['status'] ?? 'publish'),
                'published_at' => (string) ($row['published_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }, $rows);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
            'provider' => 'search-like',
        ];
    });
};
