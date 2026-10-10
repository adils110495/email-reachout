<?php

namespace App\Http\Resources;

use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MailSetting  Credentials and hosts' secrets are never included. */
class EmailAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'from_name' => $this->from_name,
            'from_email' => $this->senderEmail(),
            'daily_limit' => $this->daily_limit,
            'rate_limit_per_minute' => $this->rate_limit_per_minute,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'has_imap' => $this->hasImap(),
            'smtp_ok' => $this->smtp_ok,
            'imap_ok' => $this->imap_ok,
        ];
    }
}
