<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulk extends Model
{
    public const TYPE_VERIFY = 'verify';
    public const TYPE_FIND   = 'find';

    /** Create / update leads from a CSV with mapped columns. */
    public const TYPE_IMPORT = 'import';

    /** Sequence actions: each item's input is a lead id (enroll, unsubscribe) or an enrollment id. */
    public const TYPE_ENROLL      = 'enroll';
    public const TYPE_PAUSE       = 'pause';
    public const TYPE_RESUME      = 'resume';
    public const TYPE_REMOVE      = 'remove';
    public const TYPE_UNSUBSCRIBE = 'unsubscribe';

    public const SEQUENCE_ACTIONS = [self::TYPE_ENROLL, self::TYPE_PAUSE, self::TYPE_RESUME, self::TYPE_REMOVE, self::TYPE_UNSUBSCRIBE];

    /** An import uploaded and previewed, waiting for the user to confirm the column mapping. */
    public const STATUS_DRAFT      = 'draft';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'name',
        'type',
        'category_id',
        'options',
        'original_filename',
        'file_path',
        'status',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'options'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Human label for the run type. */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_VERIFY => 'Verify',
            self::TYPE_FIND => 'Find',
            self::TYPE_IMPORT => 'Import leads',
            self::TYPE_ENROLL => 'Enroll in sequence',
            self::TYPE_PAUSE => 'Pause enrollments',
            self::TYPE_RESUME => 'Resume enrollments',
            self::TYPE_REMOVE => 'Remove from sequence',
            self::TYPE_UNSUBSCRIBE => 'Unsubscribe leads',
            default => ucfirst((string) $this->type),
        };
    }

    public function isSequenceAction(): bool
    {
        return in_array($this->type, self::SEQUENCE_ACTIONS, true);
    }

    public function isImport(): bool
    {
        return $this->type === self::TYPE_IMPORT;
    }

    /**
     * Queue a sequence action (enroll / pause / resume / remove / unsubscribe) over many
     * records. One bulk_item per id; ProcessBulkJob works through them in chunks, so a
     * request never does the work itself however many rows are selected.
     *
     * @param  list<int>  $ids  lead ids (enroll, unsubscribe) or enrollment ids (pause, resume, remove)
     */
    public static function queueSequenceAction(string $type, array $ids, string $name, array $options = []): self
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $bulk = static::create([
            'name' => mb_substr($name, 0, 120),
            'type' => $type,
            'status' => self::STATUS_PENDING,
            'total_records' => count($ids),
            'options' => $options,
        ]);

        // The lead's address goes in `extra` right away, so the results table and its
        // search are readable while the run is still queued.
        $byEnrollment = ! in_array($type, [self::TYPE_ENROLL, self::TYPE_UNSUBSCRIBE], true);

        $now = now();
        foreach (array_chunk($ids, 500) as $chunk) {
            $emails = $byEnrollment
                ? SequenceEnrollment::join('leads', 'leads.id', '=', 'sequence_enrollments.lead_id')
                    ->whereIn('sequence_enrollments.id', $chunk)->pluck('leads.email', 'sequence_enrollments.id')
                : Lead::whereIn('id', $chunk)->pluck('email', 'id');

            BulkItem::insert(array_map(fn (int $id) => [
                'bulk_id' => $bulk->id,
                'input' => (string) $id,
                'extra' => $emails[$id] ?? null,
                'status' => BulkItem::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        \App\Jobs\ProcessBulkJob::dispatch($bulk->id)->onQueue('default');

        return $bulk;
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkItem::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    /** Category that a "find" run files its discovered leads under. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Percentage of records processed so far (0-100). */
    public function getProgressAttribute(): int
    {
        if ($this->total_records < 1) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_records / $this->total_records) * 100));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Bootstrap contextual colour for the status badge / progress bar. */
    public function getStatusColourAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_FAILED     => 'danger',
            self::STATUS_CANCELLED  => 'dark',
            default                 => 'warning',
        };
    }
}
