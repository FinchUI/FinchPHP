<?php

/**
 * Finch\Api\V1\CategoriesApi - 分类 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\TaxonomyService;

final class CategoriesApi extends BaseController
{
    public function list(): Response
    {
        $tree = filter_var($this->request->query('tree', false), FILTER_VALIDATE_BOOLEAN);

        return ApiResource::success($this->taxonomyService()->categories($tree));
    }

    public function show(string|int $id): Response
    {
        $category = $this->taxonomyService()->findCategory($id);
        if ($category === null) {
            return ApiResource::error('分类不存在', 4101, 404);
        }

        return ApiResource::success($category);
    }

    private function taxonomyService(): TaxonomyService
    {
        return new TaxonomyService($this->app->db);
    }
}
