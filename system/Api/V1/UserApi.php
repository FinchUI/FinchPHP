<?php

/**
 * Finch\Api\V1\UserApi - 用户 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;
use Finch\Model\Role;
use Finch\Model\User;

final class UserApi extends BaseController
{
    public function me(): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return ApiResource::error('未认证或 token 已失效', 3000, 401);
        }

        return ApiResource::success($this->present($user));
    }

    public function list(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = min(50, max(1, (int) $this->request->query('per_page', 20)));

        $data = User::query()->orderBy('id', 'DESC')->paginate($page, $perPage);
        $items = [];
        foreach ($data['data'] as $row) {
            $items[] = $this->presentRow($row);
        }

        $data['data'] = $items;

        return ApiResource::pagination($data);
    }

    public function show(string|int $id): Response
    {
        if (!is_numeric($id)) {
            return ApiResource::error('用户不存在', 5001, 404);
        }

        $user = User::find((int) $id);
        if (!$user instanceof User) {
            return ApiResource::error('用户不存在', 5001, 404);
        }

        return ApiResource::success($this->present($user));
    }

    private function present(User $user): array
    {
        return $this->presentRow($user->toArray());
    }

    /** @param array<string,mixed> $row */
    private function presentRow(array $row): array
    {
        $roleName = '';
        $roleSlug = '';
        $roleId = is_numeric($row['role_id'] ?? null) ? (int) $row['role_id'] : 0;

        if ($roleId > 0) {
            $role = Role::find($roleId);
            if ($role instanceof Role) {
                $roleName = (string) ($role->name ?? '');
                $roleSlug = (string) ($role->slug ?? '');
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'is_admin' => (int) ($row['is_admin'] ?? 0) === 1,
            'role' => [
                'id' => $roleId,
                'name' => $roleName,
                'slug' => $roleSlug,
            ],
            'last_login_at' => (string) ($row['last_login_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
