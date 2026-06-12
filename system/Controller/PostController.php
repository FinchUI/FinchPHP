<?php

/**
 * Finch\Controller\PostController - 文章详情
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\PostService;

final class PostController extends BaseController
{
    public function single(string $slug): Response
    {
        $post = $this->postService()->findPublishedBySlug($slug, 'article');
        if ($post === null) {
            return $this->html('<h1>文章不存在</h1>', 404);
        }

        return $this->html($this->app->template->render('single', ['post' => $post]));
    }

    private function postService(): PostService
    {
        return new PostService($this->app->db);
    }
}
