<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    // Lead status constants
    const STATUS_NEW = 'new';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_REPLIED = 'replied';

    protected $fillable = [
        'company_name',
        'website',
        'email',
        'emails',
        'linkedin',
        'status',
        'platform_id',
        'category_id',
    ];

    /**
     * Keep `email` (the primary address everything else reads) and `emails`
     * (every address, JSON) in step, whichever one the caller wrote:
     *
     *   - emails written  -> email becomes the first of them
     *   - email written   -> it goes to the front of emails; the rest are kept
     *                        (clearing email clears the list too)
     */
    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            if ($lead->isDirty('emails')) {
                $list = static::normalizeEmails($lead->emails);

                $lead->emails = $list ?: null;
                $lead->email  = $list[0] ?? null;
            } elseif ($lead->isDirty('email')) {
                $list = static::normalizeEmails(array_merge([$lead->email], $lead->emails ?? []));

                $lead->emails = ($lead->email && $list) ? $list : null;
                $lead->email  = $lead->email ?: null;
            }
        });
    }

    /**
     * Turn an array - or a string separated by commas, semicolons or spaces -
     * into a clean list: trimmed, lower-cased, valid, no duplicates.
     *
     * @param  array<int, mixed>|string|null  $input
     * @return array<int, string>
     */
    public static function normalizeEmails(array|string|null $input): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        }

        $clean = [];

        foreach ($input ?? [] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $clean[$candidate] = $candidate;
            }
        }

        return array_values($clean);
    }

    /**
     * Every address for this lead (falls back to the single `email` column
     * for rows saved before `emails` existed).
     *
     * @return array<int, string>
     */
    public function getEmailListAttribute(): array
    {
        if (! empty($this->emails)) {
            return $this->emails;
        }

        return $this->email ? [$this->email] : [];
    }

    public function platform()
    {
        return $this->belongsTo(\App\Models\Platform::class);
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    protected $casts = [
        'emails'     => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: only leads that haven't been emailed yet.
     */
    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    /**
     * Scope: leads that hold this address, as the primary email or among their others.
     */
    public function scopeHoldingAddress($query, string $address)
    {
        $address = strtolower($address);

        return $query->where(fn ($q) => $q->where('email', $address)->orWhereJsonContains('emails', $address));
    }

    /**
     * Scope: only leads with a valid email address.
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    /**
     * Check if this lead has a usable email address.
     */
    public function hasEmail(): bool
    {
        return ! empty($this->email);
    }
}
