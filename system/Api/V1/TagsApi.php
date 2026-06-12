<?php

/**
 * Finch\Api\V1\TagsApi - 标签 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\TaxonomyService;

final class TagsApi extends BaseController
{
    public function list(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = min(50, max(1, (int) $this->request->query('per_page', 20)));
        $sort = (string) $this->request->query('sort', 'name');
        $order = (string) $this->request->query('order', 'asc');

        $pageData = $this->taxonomyService()->tagsPage($page, $perPage, $sort, $order);

        return ApiResource::pagination($pageData);
    }

    public function show(string|int $id): Response
    {
        $tag = $this->taxonomyService()->findTag($id);
        if ($tag === null) {
            return ApiResource::error('标签不存在', 4201, 404);
        }

        return ApiResource::success($tag);
    }

    private function taxonomyService(): TaxonomyService
    {
        return new TaxonomyService($this->app->db);
    }
}
