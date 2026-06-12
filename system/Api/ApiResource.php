<?php

/**
 * Finch\Api\ApiResource - API 统一响应
 */

declare(strict_types=1);

namespace Finch\Api;

use Finch\Core\Response;

final class ApiResource
{
    /** @param array<string, mixed>|null $meta */
    public static function success(mixed $data = null, string $message = 'success', ?array $meta = null, int $status = 200): Response
    {
        $payload = [
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return (new Response())->json($payload, $status);
    }

    /** @param array<string, mixed> $errors */
    public static function error(string $message, int $code = 1000, int $status = 400, array $errors = []): Response
    {
        $payload = [
            'code'    => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return (new Response())->json($payload, $status);
    }

    /** @param array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,last_page:int} $page */
    public static function pagination(array $page, string $message = 'success'): Response
    {
        return self::success($page['data'], $message, [
            'total'        => $page['total'],
            'per_page'     => $page['per_page'],
            'current_page' => $page['page'],
            'last_page'    => $page['last_page'],
        ]);
    }
}
