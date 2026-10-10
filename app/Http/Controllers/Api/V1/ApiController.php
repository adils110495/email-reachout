<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/** The API works on the same team-shared data as the web app; a valid token is the gate. */
abstract class ApiController extends Controller
{
    protected function user(): User
    {
        /** @var User */
        return request()->user();
    }

    protected function perPage(Request $request): int
    {
        $max = (int) config('sequencer.api.max_per_page', 100);

        return max(1, min($max, $request->integer('per_page', (int) config('sequencer.api.per_page', 25))));
    }
}
