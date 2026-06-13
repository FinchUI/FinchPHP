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

        // 调试：写入日志便于排查前台文章不显示问题
        $this->app->logger->debug('HomeController::index', [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $pageData['total'],
            'data_count' => count($pageData['data']),
            'gmdate' => gmdate('Y-m-d H:i:s'),
            'date' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
        ]);

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
