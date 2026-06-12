<?php

/**
 * Finch\Controller\CategoryController - 分类列表
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\PostService;
use Finch\Service\TaxonomyService;

final class CategoryController extends BaseController
{
    public function list(string $slug): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, (int) $this->app->settings->get('posts_per_page', 10));

        $category = $this->taxonomyService()->findCategory($slug);
        if ($category === null) {
            return $this->html('<h1>分类不存在</h1>', 404);
        }

        $pageData = $this->postService()->publishedByCategory($slug, $page, $perPage);

        return $this->html($this->app->template->render('index', [
            'query' => [
                'mode' => 'category',
                'title' => '分类：' . $category['name'],
                'slug' => $category['slug'],
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
