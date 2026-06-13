<?php

/**
 * Finch\Api\V1\ModulesApi - 模块 API
 *
 * 侧栏模块的读取接口（见 PROJECT_PLAN §4.9）。
 * 提供模块列表和详情的 API 访问。
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Service\ModuleService;

final class ModulesApi extends BaseController
{
    public function list(): Response
    {
        $sidebar = (string) $this->request->query('sidebar', 'side1');
        $type = (string) $this->request->query('type', '');

        $modules = $this->moduleService()->listBySidebar($sidebar);

        if ($type !== '') {
            $modules = array_filter($modules, static fn (array $m): bool => ($m['type'] ?? '') === $type);
        }

        // 仅返回启用的模块
        $modules = array_filter($modules, static fn (array $m): bool => (bool) ($m['is_active'] ?? false));

        $data = array_map(static function (array $module): array {
            return [
                'id' => (int) ($module['id'] ?? 0),
                'name' => (string) ($module['name'] ?? ''),
                'type' => (string) ($module['type'] ?? 'custom'),
                'sidebar' => (string) ($module['sidebar'] ?? 'side1'),
                'position' => (int) ($module['position'] ?? 0),
                'content' => (string) ($module['content'] ?? ''),
            ];
        }, array_values($modules));

        return ApiResource::success($data);
    }

    public function show(string|int $id): Response
    {
        if (!is_numeric($id)) {
            return ApiResource::error('模块不存在', 4001, 404);
        }

        $module = $this->moduleService()->findById((int) $id);
        if ($module === null) {
            return ApiResource::error('模块不存在', 4001, 404);
        }

        return ApiResource::success([
            'id' => (int) ($module['id'] ?? 0),
            'name' => (string) ($module['name'] ?? ''),
            'type' => (string) ($module['type'] ?? 'custom'),
            'sidebar' => (string) ($module['sidebar'] ?? 'side1'),
            'position' => (int) ($module['position'] ?? 0),
            'content' => (string) ($module['content'] ?? ''),
            'config' => (string) ($module['config'] ?? ''),
            'is_active' => (bool) ($module['is_active'] ?? false),
        ]);
    }

    private function moduleService(): ModuleService
    {
        return new ModuleService(FP_CONTENT_DIR . '/modules', $this->app->cache);
    }
}
