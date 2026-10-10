<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EmailAccountResource;
use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only: sending accounts (and their credentials) are managed under Settings > Mail Settings. */
class EmailAccountController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return EmailAccountResource::collection(
            MailSetting::orderByDesc('is_default')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function show(MailSetting $account): EmailAccountResource
    {
        return new EmailAccountResource($account);
    }
}
