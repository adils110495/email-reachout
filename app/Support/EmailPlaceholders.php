<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Placeholders in an email's subject and body: {{client_name}} and friends,
 * filled per recipient when the email goes out. Templates written before
 * these existed used [Client Name] and the like - those still work.
 */
final class EmailPlaceholders
{
    /** {{key}} => what it becomes; the editors' "Insert" chips list these. */
    public const TOKENS = [
        'client_name'    => "The contact's name",
        'sender_name'    => 'Your name',
        'sender_company' => 'Your company',
    ];

    /** Other ways of writing the same keys. */
    private const ALIASES = [
        'name'         => 'client_name',
        'company_name' => 'client_name',
        'your_name'    => 'sender_name',
        'your_company' => 'sender_company',
    ];

    /** The older bracket style. */
    private const LEGACY = [
        '[Client Name]'       => 'client_name',
        '[Company Name]'      => 'client_name',
        '[Your Company Name]' => 'sender_company',
        '[Your Name]'         => 'sender_name',
        '[Sender Name]'       => 'sender_name',
        '{name}'              => 'client_name',
    ];

    /**
     * The values for one recipient. Call it in the request: env() is
     * unreliable in a queue worker once config is cached.
     *
     * @return array<string, string>
     */
    public static function values(?string $clientName): array
    {
        $senderName = (string) env('SENDER_NAME', 'Sales Team');

        return [
            'client_name'    => trim((string) $clientName),
            'sender_name'    => $senderName,
            'sender_company' => AppSetting::companyName(),
        ];
    }

    /**
     * Fill in {{ key }} (spaces and case do not matter) and the bracket style.
     * An unknown key is left as written, so a typo shows in the email instead
     * of quietly vanishing.
     *
     * @param  array<string, string>  $values
     * @param  bool  $html  the text is HTML, so the values are escaped
     */
    public static function fill(string $text, array $values, bool $html = false): string
    {
        $value = function (string $key) use ($values, $html): ?string {
            $key = strtolower($key);
            $key = self::ALIASES[$key] ?? $key;

            if (! array_key_exists($key, $values)) {
                return null;
            }

            return $html ? e($values[$key]) : $values[$key];
        };

        $text = preg_replace_callback('/\{\{\s*([A-Za-z_]+)\s*\}\}/', fn (array $m) => $value($m[1]) ?? $m[0], $text);

        foreach (self::LEGACY as $token => $key) {
            $text = str_ireplace($token, $value($key) ?? $token, $text);
        }

        return $text;
    }
}
