<?php

namespace App\Http\Controllers;

use App\Services\UserAccessService;

abstract class Controller
{
    /** Who the request is handling data for - see UserAccessService. */
    protected function access(): UserAccessService
    {
        return app(UserAccessService::class);
    }
}
