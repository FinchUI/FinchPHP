<?php

/**
 * Finch\Core\Validation - 输入验证入口
 */

declare(strict_types=1);

namespace Finch\Core;

use Finch\Validation\Validator;

final class Validation
{
    /**
     * @param Request|array<string, mixed> $input
     * @param array<string, string|list<string>> $rules
     */
    public static function make(Request|array $input, array $rules): Validator
    {
        $data = $input instanceof Request ? $input->all() : $input;
        $validator = new Validator($data, $rules);
        $validator->validate();

        return $validator;
    }
}
