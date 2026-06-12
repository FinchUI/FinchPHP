<?php

/**
 * Finch\Api\V1\CommentsApi - 评论 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\CommentService;

final class CommentsApi extends BaseController
{
    public function list(string|int $postId): Response
    {
        if (!is_numeric($postId)) {
            return ApiResource::error('文章不存在', 4001, 404);
        }

        $service = $this->commentService();
        if ($service->findPublishedPost((int) $postId) === null) {
            return ApiResource::error('文章不存在', 4001, 404);
        }

        if ($this->app->settings->get('comment_enabled', true) !== true) {
            return ApiResource::error('评论已禁用', 6001, 403);
        }

        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = min(50, max(1, (int) $this->request->query('per_page', 20)));

        return ApiResource::pagination($service->approvedPage((int) $postId, $page, $perPage));
    }

    public function store(string|int $postId): Response
    {
        if (!is_numeric($postId)) {
            return ApiResource::error('文章不存在', 4001, 404);
        }

        $rules = [
            'content'   => 'required|min:1|max:5000',
            'parent_id' => 'numeric',
        ];

        if ($this->currentUser() === null) {
            $rules['author_name'] = 'required|max:100';
            $rules['author_email'] = 'required|email|max:100';
        }

        $validator = $this->validate($rules);
        if ($validator->fails()) {
            return ApiResource::error('评论参数错误', 6006, 422, $validator->errors());
        }

        $result = $this->commentService()->submit((int) $postId, $this->request->all(), $this->currentUser(), $this->request);
        if ($result['ok'] !== true) {
            return ApiResource::error($result['message'], $result['code'], $result['status']);
        }

        return ApiResource::success($result['comment'], $result['message'], null, $result['status']);
    }

    private function commentService(): CommentService
    {
        return new CommentService($this->app->db, $this->app->settings);
    }
}
