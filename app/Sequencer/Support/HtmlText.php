<?php

namespace App\Sequencer\Support;

/** Derives a readable text/plain alternative from an HTML body. */
final class HtmlText
{
    public static function fromHtml(string $html): string
    {
        // Keep link targets visible: <a href="u">label</a> => label (u)
        $text = preg_replace_callback(
            '#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function (array $m) {
                $label = trim(strip_tags($m[3]));
                $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);

                return ($label === '' || $label === $url || str_starts_with($url, 'mailto:')) ? ($label ?: $url) : "$label ($url)";
            },
            $html
        ) ?? $html;

        $text = preg_replace('#<(script|style|head)\b.*?</\1>#is', '', $text) ?? $text;
        $text = preg_replace('#<img\b[^>]*>#i', '', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote)>#i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** True when the string contains markup (anything strip_tags would change). */
    public static function isHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }
}
