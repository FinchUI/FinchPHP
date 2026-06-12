<?php

/**
 * Finch\Controller\SearchController - 搜索结果
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\SearchService;

final class SearchController extends BaseController
{
    public function index(): Response
    {
        $keyword = trim((string) $this->request->query('q', ''));
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, (int) $this->app->settings->get('posts_per_page', 10));

        $pageData = $this->searchService()->searchPublished($keyword, $page, $perPage);

        return $this->html($this->app->template->render('index', [
            'query' => [
                'mode' => 'search',
                'title' => $keyword === '' ? '搜索' : '搜索：' . $keyword,
                'keyword' => $keyword,
                'provider' => (string) ($pageData['provider'] ?? 'search-like'),
                'items' => $pageData['data'],
                'total' => $pageData['total'],
                'page' => $pageData['page'],
                'per_page' => $pageData['per_page'],
                'last_page' => $pageData['last_page'],
            ],
        ]));
    }

    private function searchService(): SearchService
    {
        return new SearchService($this->app->db, $this->app->settings);
    }
}
