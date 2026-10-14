<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class User extends Authenticatable
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_USER        = 'user';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_USER        => 'User',
    ];

    /**
     * Modules a user can be given, keyed by the name the routes (`module:` middleware)
     * and the sidebar (config/navigation.php) use. The dashboard and the
     * notification bell are always available; Branding and Users are super admin only.
     */
    public const MODULES = [
        'finder'         => 'Finder',
        'verifier'       => 'Verifier',
        'bulks'          => 'Bulks',
        'leads'          => 'Leads',
        'gmb_leads'      => 'GMB Leads',
        'deals'          => 'Deals',
        'email_activity' => 'Email Activity',
        'templates'      => 'Email Templates',
        'platforms'      => 'Platforms',
        'categories'     => 'Categories',
        'addresses'      => 'Addresses',
        'mail_settings'  => 'Mail Settings',
    ];

    /** Platforms every new account starts with - lead searches file under "Google". */
    public const DEFAULT_PLATFORMS = ['Google', 'LinkedIn', 'Upwork', 'Freelancing', 'Facebook'];

    /** A user may pick a new username on their Profile page once in this many days. */
    public const USERNAME_CHANGE_DAYS = 30;

    // role, is_active and modules are deliberately NOT fillable: the signup form
    // must never be able to grant them. Only UserController sets them (forceFill).
    protected $fillable = ['name', 'username', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'role'      => self::ROLE_USER,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        // Assigning a plain password hashes it on the way in.
        return [
            'password'            => 'hashed',
            'is_active'           => 'boolean',
            'modules'             => 'array',
            'username_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Each account has its own settings, so give a new one the platforms
        // the lead searches expect rather than an empty list.
        static::created(function (User $user) {
            $now = now();

            DB::table('platforms')->insert(array_map(fn (string $name) => [
                'user_id'    => $user->id,
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULT_PLATFORMS));
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * modules = null means every module (the default for a new account);
     * a list means only those. A super admin always has everything.
     */
    public function canUseModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->modules === null || in_array($module, $this->modules, true);
    }

    /** @return array<int, string> */
    public function enabledModules(): array
    {
        return array_values(array_filter(array_keys(self::MODULES), fn (string $m) => $this->canUseModule($m)));
    }

    /**
     * Rules for a username, shared by the Profile page and the Users module.
     * alpha_dash keeps "@" and "." out, so a username can never be mistaken
     * for an email address at sign-in.
     *
     * @return array<int, mixed>
     */
    public static function usernameRules(?self $ignore = null): array
    {
        return ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($ignore?->id)];
    }

    /**
     * A free username for a new account. Sign-up does not ask for one, so it
     * is built from the email address (jane.doe+news@example.com -> jane_doe),
     * or from the name when that is too short; a number is added while it is
     * taken (jane_doe2, jane_doe3...). The user can change it on their Profile.
     */
    public static function uniqueUsernameFrom(string $email, string $name = ''): string
    {
        $local = Str::before(Str::before($email, '@'), '+');

        $base = collect([$local, $name])
            ->map(fn (string $value) => substr(Str::slug(str_replace('.', ' ', $value), '_'), 0, 40))
            ->first(fn (string $slug) => strlen($slug) >= 3) ?? 'user';

        $username = $base;

        for ($n = 2; static::where('username', $username)->exists(); $n++) {
            // Past a few dozen collisions a counter is only a guessing game.
            $username = $n <= 50 ? $base.$n : $base.'_'.Str::lower(Str::random(6));
        }

        return $username;
    }

    /**
     * The first change is free - the username was generated at sign-up -
     * after that, once every USERNAME_CHANGE_DAYS days.
     */
    public function canChangeUsername(): bool
    {
        $availableAt = $this->usernameChangeAvailableAt();

        return $availableAt === null || $availableAt->lte(now());
    }

    /** When the username can next be changed; null when it never has been. */
    public function usernameChangeAvailableAt(): ?Carbon
    {
        return $this->username_changed_at?->copy()->addDays(self::USERNAME_CHANGE_DAYS);
    }

    /**
     * The account an artisan command acts for: --user as an id or username,
     * or the first super admin when none is given.
     */
    public static function resolveForConsole(?string $user): ?self
    {
        if ($user === null || $user === '') {
            return static::where('role', self::ROLE_SUPER_ADMIN)->orderBy('id')->first();
        }

        return ctype_digit($user) ? static::find((int) $user) : static::where('username', $user)->first();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
