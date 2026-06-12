<?php

/**
 * Finch\Api\V1\SystemApi - 系统信息 API
 */

declare(strict_types=1);

namespace Finch\Api\V1;

use Finch\Api\ApiResource;
use Finch\Controller\BaseController;
use Finch\Core\Response;

final class SystemApi extends BaseController
{
    public function info(): Response
    {
        return ApiResource::success([
            'name'     => $this->app->settings->get('site_name', 'Finch'),
            'subtitle' => $this->app->settings->get('site_subtitle', ''),
            'url'      => $this->app->settings->get('site_url', ''),
            'timezone' => $this->app->settings->get('site_timezone', date_default_timezone_get()),
            'version'  => '1.0.0',
        ]);
    }
}
