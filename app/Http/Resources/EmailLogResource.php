<?php

namespace App\Http\Resources;

use App\Models\LeadEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadEmail  The rendered body and tracking token are deliberately not exposed.
 *
 * `status` is the headline state (bounced > replied > clicked > opened > delivery state);
 * `delivery_status` is the stored pipeline state (queued, sending, sent, failed, bounced).
 */
class EmailLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence_id' => $this->sequence_id,
            'sequence_step_id' => $this->sequence_step_id,
            'enrollment_id' => $this->enrollment_id,
            'contact_id' => $this->lead_id,
            'email_account_id' => $this->mail_setting_id,
            'message_id' => $this->message_id,
            'from_email' => $this->from_email,
            'to_email' => $this->to_email,
            'subject' => $this->subject,
            'status' => $this->engagement()->value,
            'delivery_status' => $this->status,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'opened_at' => $this->first_opened_at?->toIso8601String(),
            'open_count' => $this->open_count,
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'click_count' => $this->click_count,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'bounced_at' => $this->bounced_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
