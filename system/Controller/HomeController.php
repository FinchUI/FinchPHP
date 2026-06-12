<?php

/**
 * Finch\Controller\HomeController - 首页/默认路由控制器
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\PostService;

final class HomeController extends BaseController
{
    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, (int) $this->app->settings->get('posts_per_page', 10));
        $pageData = $this->postService()->publishedPage($page, $perPage, 'article');

        return $this->html($this->app->template->render('index', [
            'query' => [
                'mode' => 'home',
                'title' => (string) $this->app->settings->get('site_name', 'Finch'),
                'items' => $pageData['data'],
                'total' => $pageData['total'],
                'page' => $pageData['page'],
                'per_page' => $pageData['per_page'],
                'last_page' => $pageData['last_page'],
            ],
        ]));
    }

    private function postService(): PostService
    {
        return new PostService($this->app->db);
    }
}
