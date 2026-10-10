<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A group of leads ("Agencies", "SaaS companies"...). Also the sequencer's contact
 * lists: a lead can sit in several categories through the category_lead pivot;
 * leads.category_id remains the lead's primary category and is kept in the pivot.
 */
class Category extends Model
{
    protected $fillable = ['name', 'description', 'status'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'category_lead')->withPivot('created_at');
    }

    /**
     * Add leads to this list. Already-present leads are skipped.
     *
     * @param  iterable<int>  $leadIds
     * @return int how many were newly added
     */
    public function addLeads(iterable $leadIds): int
    {
        $added = 0;
        $ids = array_values(array_unique(array_map('intval', is_array($leadIds) ? $leadIds : iterator_to_array($leadIds))));

        foreach (array_chunk($ids, 1000) as $chunk) {
            $existing = Lead::whereIn('id', $chunk)->pluck('id')->all();
            $present = $this->leads()->whereIn('leads.id', $existing)->pluck('leads.id')->all();
            $missing = array_values(array_diff($existing, $present));

            if ($missing) {
                $this->leads()->attach($missing, ['created_at' => now()]);
                $added += count($missing);
            }
        }

        return $added;
    }

    /** @param  iterable<int>  $leadIds */
    public function removeLeads(iterable $leadIds): int
    {
        $ids = array_values(array_unique(array_map('intval', is_array($leadIds) ? $leadIds : iterator_to_array($leadIds))));
        $removed = 0;

        foreach (array_chunk($ids, 1000) as $chunk) {
            $removed += $this->leads()->detach($chunk);
            // A lead whose primary category this was loses it too.
            Lead::whereIn('id', $chunk)->where('category_id', $this->id)->update(['category_id' => null]);
        }

        return $removed;
    }
}
