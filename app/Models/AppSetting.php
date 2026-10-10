<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    // Branding keys - each holds a path relative to public/ (e.g. uploads/branding/x.png).
    // The admin logo is also the logo in outgoing emails.
    public const ADMIN_LOGO = 'admin_logo';
    public const ADMIN_ICON = 'admin_icon';

    // Shipped artwork used until something is uploaded under Settings > Branding.
    public const DEFAULTS = [
        self::ADMIN_LOGO => 'images/sabright-logo.png',
        self::ADMIN_ICON => null, // falls back to the admin logo, cropped to its mark
    ];

    // Brand name - admin panel, email footer and AI prompts.
    public const COMPANY_NAME         = 'company_name';
    public const DEFAULT_COMPANY_NAME = 'SabRight';

    /** Footer social icons, in display order. An icon is shown only when its URL is set. */
    public const SOCIALS = [
        'social_linkedin'  => ['label' => 'LinkedIn',  'glyph' => 'in'],
        'social_facebook'  => ['label' => 'Facebook',  'glyph' => 'f'],
        'social_x'         => ['label' => 'X',         'glyph' => 'X'],
        'social_instagram' => ['label' => 'Instagram', 'glyph' => 'ig'],
        'social_youtube'   => ['label' => 'YouTube',   'glyph' => '&#9654;'],
    ];

    protected $fillable = ['key', 'value'];

    /** Per-request cache - layouts ask for the logo several times per page. */
    private static ?array $cache = null;

    public static function read(string $key, ?string $default = null): ?string
    {
        if (static::$cache === null) {
            static::$cache = Schema::hasTable('app_settings')
                ? static::pluck('value', 'key')->all()
                : [];
        }

        return static::$cache[$key] ?? $default;
    }

    public static function write(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    /**
     * Path (relative to public/) of the uploaded file for a branding key, or
     * the default when nothing was uploaded or the file has gone missing.
     */
    public static function brandingPath(string $key): ?string
    {
        $path = static::read($key);

        return $path && is_file(public_path($path)) ? $path : static::DEFAULTS[$key];
    }

    public static function isCustom(string $key): bool
    {
        return static::brandingPath($key) !== static::DEFAULTS[$key];
    }

    /**
     * Browser URL for a branding image; filemtime busts the cache on replacement.
     */
    public static function brandingUrl(string $key): ?string
    {
        $path = static::brandingPath($key);

        return $path ? asset($path).'?v='.filemtime(public_path($path)) : null;
    }

    public static function adminLogoUrl(): string
    {
        return static::brandingUrl(self::ADMIN_LOGO);
    }

    /** Small square mark - collapsed sidebar, mobile header and favicon. */
    public static function adminIconUrl(): string
    {
        return static::brandingUrl(self::ADMIN_ICON) ?? static::adminLogoUrl();
    }

    /**
     * Absolute URL of the admin logo for outgoing emails. Built from APP_URL (not
     * the request host) so it is also correct when rendered from a queue worker.
     */
    public static function emailLogoUrl(): string
    {
        $path = static::brandingPath(self::ADMIN_LOGO);

        return rtrim((string) config('app.url'), '/').'/'.$path.'?v='.filemtime(public_path($path));
    }

    /** Brand name set under Settings > Branding. */
    public static function companyName(): string
    {
        return static::read(self::COMPANY_NAME) ?: self::DEFAULT_COMPANY_NAME;
    }

    /** @return array<int, array{label: string, glyph: string, url: string}> */
    public static function socialLinks(): array
    {
        $links = [];
        foreach (static::SOCIALS as $key => $social) {
            if ($url = static::read($key)) {
                $links[] = $social + ['url' => $url];
            }
        }

        return $links;
    }

    /** Drop the cached values - a long-lived queue worker must see fresh settings. */
    public static function flush(): void
    {
        static::$cache = null;
    }
}
