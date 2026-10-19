<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\ForgetsUnreadableSecrets;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's own SerpAPI key and search defaults - one row per user.
 *
 * Personal even to a super admin, like Mail Settings: always read through
 * forActingUser() (mine()), so a search never spends another user's credits.
 * Which key is actually used is SerpApiConfigService's call.
 */
class SerpapiSetting extends Model
{
    use BelongsToUser, ForgetsUnreadableSecrets;

    protected $fillable = ['api_key', 'country', 'language', 'is_active'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key'   => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /** The acting user's own row, or null when they have not saved one. */
    public static function forActingUser(): ?self
    {
        return static::mine()->first();
    }

    public function hasKey(): bool
    {
        return filled($this->getRawOriginal('api_key'));
    }

    /** The saved key while the setting is switched on, else null. */
    public function activeKey(): ?string
    {
        return $this->is_active && $this->hasKey() ? $this->api_key : null;
    }
}
