<?php

/**
 * Finch\Controller\PageController - 页面详情
 */

declare(strict_types=1);

namespace Finch\Controller;

use Finch\Core\Response;
use Finch\Service\PostService;

final class PageController extends BaseController
{
    public function single(string $slug): Response
    {
        $page = $this->postService()->findPublishedBySlug($slug, 'page');
        if ($page === null) {
            return $this->html('<h1>页面不存在</h1>', 404);
        }

        return $this->html($this->app->template->render('page', ['post' => $page]));
    }

    private function postService(): PostService
    {
        return new PostService($this->app->db);
    }
}
