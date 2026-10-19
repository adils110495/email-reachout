<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * A contact of a coexistence number: synced from the WhatsApp Business app's
 * address book (smb_app_state_sync), or met in its imported chat history.
 */
class WhatsappContact extends Model
{
    use BelongsToUser;

    public const SOURCE_APP     = 'app';
    public const SOURCE_HISTORY = 'history';

    protected $fillable = ['phone', 'name', 'source', 'removed_at'];

    protected $casts = ['removed_at' => 'datetime'];
}
