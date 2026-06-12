<?php

/**
 * Finch\Service\SearchService - 搜索门面（LIKE 回退）
 */

declare(strict_types=1);

namespace Finch\Service;

use Finch\Core\Cache;
use Finch\Core\Database;
use Finch\Core\Settings;

final class SearchService
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int,provider:string}
     */
    public function searchPublished(string $keyword, int $page = 1, int $perPage = 10): array
    {
        $provider = trim((string) $this->settings->get('search_provider', 'search-like'));
        if ($provider === '') {
            $provider = 'search-like';
        }

        $cacheKey = 'published:' . sha1((string) json_encode([
            'provider' => $provider,
            'keyword' => trim($keyword),
            'page' => $page,
            'per_page' => $perPage,
        ]));

        $cache = new Cache($this->db);
        $cached = $cache->remember($cacheKey, 180, function () use ($keyword, $page, $perPage, $provider): array {
            $providerResult = ProviderRuntime::dispatch('search', $provider, 'query', [
                'keyword' => trim($keyword),
                'page' => max(1, $page),
                'per_page' => max(1, $perPage),
            ]);

            $normalized = $this->normalizeProviderResult($providerResult, $page, $perPage, $provider);
            if ($normalized !== null) {
                return $normalized;
            }

            // Stage19 minimum contract: deterministic fallback to local LIKE search.
            $pageData = $this->likeSearchPublished($keyword, $page, $perPage);
            $pageData['provider'] = $provider;

            return $pageData;
        }, 'search');

        if (is_array($cached)) {
            return $cached;
        }

        $pageData = $this->likeSearchPublished($keyword, $page, $perPage);
        $pageData['provider'] = $provider;

        return $pageData;
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int,provider:string}|null
     */
    private function normalizeProviderResult(mixed $providerResult, int $page, int $perPage, string $provider): ?array
    {
        if (!is_array($providerResult)) {
            return null;
        }

        if (!isset($providerResult['data']) || !is_array($providerResult['data'])) {
            return null;
        }

        $data = [];
        foreach ($providerResult['data'] as $row) {
            if (is_array($row)) {
                $data[] = $row;
            }
        }

        $resolvedPage = isset($providerResult['page']) && is_numeric($providerResult['page'])
            ? max(1, (int) $providerResult['page'])
            : max(1, $page);
        $resolvedPerPage = isset($providerResult['per_page']) && is_numeric($providerResult['per_page'])
            ? max(1, (int) $providerResult['per_page'])
            : max(1, $perPage);
        $total = isset($providerResult['total']) && is_numeric($providerResult['total'])
            ? max(0, (int) $providerResult['total'])
            : count($data);

        $lastPage = isset($providerResult['last_page']) && is_numeric($providerResult['last_page'])
            ? max(0, (int) $providerResult['last_page'])
            : (int) ceil($total / $resolvedPerPage);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $resolvedPage,
            'per_page' => $resolvedPerPage,
            'last_page' => $lastPage,
            'provider' => isset($providerResult['provider']) && is_scalar($providerResult['provider'])
                ? (string) $providerResult['provider']
                : $provider,
        ];
    }

    /**
     * @return array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int}
     */
    private function likeSearchPublished(string $keyword, int $page = 1, int $perPage = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [
                'data' => [],
                'total' => 0,
                'page' => max(1, $page),
                'per_page' => max(1, $perPage),
                'last_page' => 0,
            ];
        }

        $prefix = $this->db->prefix();
        $like = '%' . $keyword . '%';

        $countRow = $this->db->selectOne(
            "SELECT COUNT(*) AS aggregate FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)',
            ['publish', 'article', gmdate('Y-m-d H:i:s'), $like, $like, $like],
        );

        $total = (int) ($countRow['aggregate'] ?? 0);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.type, p.status, p.published_at, p.created_at, p.updated_at "
            . "FROM {$prefix}post p "
            . 'WHERE p.status = ? AND p.type = ? AND p.deleted_at IS NULL AND p.published_at <= ? '
            . 'AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) '
            . 'ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            ['publish', 'article', gmdate('Y-m-d H:i:s'), $like, $like, $like, $perPage, $offset],
        );

        $data = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
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
        ];
    }
}
