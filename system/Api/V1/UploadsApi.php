<?php

/**
 * Finch\Api\V1\UploadsApi - 上传 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Asset;
use Finch\Core\Response;
use Finch\Core\Settings;
use Finch\Service\UploadService;
use RuntimeException;

final class UploadsApi extends BaseController
{
    public function list(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = min(100, max(1, (int) $this->request->query('per_page', 20)));

        return ApiResource::pagination($this->service()->page($page, $perPage));
    }

    public function store(): Response
    {
        $userId = isset($this->app->user) && $this->app->user !== null ? (int) $this->app->user->id : null;
        $postIdInput = $this->request->input('post_id');
        $postId = is_numeric($postIdInput) ? (int) $postIdInput : null;

        try {
            $payload = $this->service()->storeFromRequest($this->request->file('file'), $userId, $postId);
        } catch (RuntimeException $e) {
            return ApiResource::error($e->getMessage(), 7001, 422);
        }

        return ApiResource::success($payload, '上传成功', null, 201);
    }

    private function service(): UploadService
    {
        $asset = isset($this->app->asset) ? $this->app->asset : null;
        $settings = isset($this->app->settings) ? $this->app->settings : null;

        return new UploadService(
            $this->app->db,
            $asset instanceof Asset ? $asset : null,
            $settings instanceof Settings ? $settings : null,
        );
    }
}
