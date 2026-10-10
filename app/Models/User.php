<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'timezone', 'settings'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        // Assigning a plain password hashes it on the way in.
        return [
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /** IANA timezone used to display times and as the default for new sequences. */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    /** Read a per-user preference with a fallback. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }
}
