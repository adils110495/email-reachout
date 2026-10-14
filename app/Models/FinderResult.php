<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinderResult extends Model
{
    public const MODE_DOMAIN = 'domain';
    public const MODE_PERSON = 'person';

    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_PATTERN = 'pattern';

    protected $fillable = [
        'mode',
        'domain',
        'person',
        'company',
        'email',
        'emails',
        'status',
        'score',
        'reason',
        'source',
        'guessed',
        'type',
        'pattern',
        'lead_id',
    ];

    protected $casts = [
        'emails'  => 'array',
        'guessed' => 'boolean',
        'score'   => 'integer',
    ];

    /** The lead this address was filed as, once it has been saved. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Every address found for this website (falls back to the single `email`
     * column for rows written before `emails` existed).
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

    /** Already in the lead database? */
    public function isSaved(): bool
    {
        return $this->lead_id !== null;
    }

    /** Bootstrap contextual colour for the verdict badge. */
    public function getStatusColourAttribute(): string
    {
        return EmailVerification::colourFor($this->status);
    }
}
