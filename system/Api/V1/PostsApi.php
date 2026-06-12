<?php

/**
 * Finch\Api\V1\PostsApi - 文章 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\PostService;

final class PostsApi extends BaseController
{
    public function list(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = min(50, max(1, (int) $this->request->query('per_page', $this->app->settings->get('posts_per_page', 10))));
        $type = (string) $this->request->query('type', 'article');
        $sort = (string) $this->request->query('sort', 'published_at');
        $order = (string) $this->request->query('order', 'desc');

        $pageData = $this->postService()->publishedPage($page, $perPage, $type, $sort, $order);

        return ApiResource::pagination($pageData);
    }

    public function show(string|int $id): Response
    {
        if (!is_numeric($id)) {
            return ApiResource::error('文章不存在', 4001, 404);
        }

        $post = $this->postService()->findPublished((int) $id);
        if ($post === null) {
            return ApiResource::error('文章不存在', 4001, 404);
        }

        return ApiResource::success($post);
    }

    private function postService(): PostService
    {
        return new PostService($this->app->db);
    }
}
