<?php

namespace App\Models;

use App\Sequencer\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A prospect. Also the sequencer's contact: person fields, custom fields, list
 * membership (categories), and a contactability status separate from the outreach
 * `status`:
 *
 *   status          new | sent | failed | replied            (where outreach stands)
 *   contact_status  active | unsubscribed | bounced | invalid | archived  (may we email at all)
 *
 * contact_status, the unsubscribe token and the bounce / unsubscribe times are
 * system-managed and never mass-assigned.
 */
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
        'first_name',
        'last_name',
        'job_title',
        'phone',
        'country',
        'custom_fields',
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
        static::creating(function (Lead $lead) {
            $lead->unsubscribe_token ??= Str::random(48);
            $lead->contact_status ??= ContactStatus::Active;
        });

        // The primary category is always also a list membership.
        static::saved(function (Lead $lead) {
            if ($lead->category_id && ($lead->wasRecentlyCreated || $lead->wasChanged('category_id'))) {
                $lead->categories()->syncWithoutDetaching([$lead->category_id => ['created_at' => now()]]);
            }
        });

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
        'custom_fields' => 'array',
        'contact_status' => ContactStatus::class,
        'unsubscribed_at' => 'datetime',
        'bounced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Every list (category) the lead belongs to, including its primary category. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_lead')->withPivot('created_at');
    }

    public function leadEmails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    /** "Ann Lee", falling back to the company, then the address. */
    public function displayName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''))
            ?: ($this->company_name ?: (string) $this->email);
    }

    /** Only active leads with an address may receive sequence email. */
    public function canReceiveEmail(): bool
    {
        return $this->hasEmail() && ($this->contact_status?->canReceiveEmail() ?? true);
    }

    /** Free-text search across name, company and every address. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('email', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('website', 'like', $like));
    }

    /** Leads in a list: their primary category or any extra membership. */
    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('category_id', $categoryId)
            ->orWhereHas('categories', fn (Builder $c) => $c->where('categories.id', $categoryId)));
    }

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
