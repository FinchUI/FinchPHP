<?php

/**
 * Finch\Controller\TagController - 标签列表
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\PostService;
use Finch\Service\TaxonomyService;

final class TagController extends BaseController
{
    public function list(string $slug): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, (int) $this->app->settings->get('posts_per_page', 10));

        $tag = $this->taxonomyService()->findTag($slug);
        if ($tag === null) {
            return $this->html('<h1>标签不存在</h1>', 404);
        }

        $pageData = $this->postService()->publishedByTag($slug, $page, $perPage);

        return $this->html($this->app->template->render('index', [
            'query' => [
                'mode' => 'tag',
                'title' => '标签：' . $tag['name'],
                'slug' => $tag['slug'],
                'items' => $pageData['data'],
                'total' => $pageData['total'],
                'page' => $pageData['page'],
                'per_page' => $pageData['per_page'],
                'last_page' => $pageData['last_page'],
            ],
        ]));
    }

    private function taxonomyService(): TaxonomyService
    {
        return new TaxonomyService($this->app->db);
    }

    private function postService(): PostService
    {
        return new PostService($this->app->db);
    }
}
