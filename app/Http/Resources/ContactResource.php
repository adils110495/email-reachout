<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead  A contact is a lead. `status` is whether it may be emailed; `outreach_status` is the Leads pipeline state. */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'emails' => $this->email_list,
            'company' => $this->company_name,
            'website' => $this->website,
            'linkedin' => $this->linkedin,
            'phone' => $this->phone,
            'country' => $this->country,
            'job_title' => $this->job_title,
            'status' => $this->contact_status?->value ?? 'active',
            'outreach_status' => $this->status,
            'custom_fields' => $this->custom_fields ?? (object) [],
            'category_id' => $this->category_id,
            'lists' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'bounced_at' => $this->bounced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
