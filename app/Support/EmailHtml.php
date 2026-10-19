<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Email bodies are written in the rich-text editor (HTML), or are plain text -
 * emails sent before the editor, the AI writer, a browser where the editor
 * did not load. render() turns either into HTML that is safe to send and to
 * show inside the app.
 *
 * clean() keeps an allowlist of formatting tags and attributes only - no
 * scripts, styles, event handlers or javascript: links. A body one user wrote
 * is shown to a super admin too, so it must never be able to run in the app.
 */
final class EmailHtml
{
    /** Tag => the attributes it may keep. */
    private const ALLOWED = [
        'p' => ['style'], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h1' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
        'ul' => [], 'ol' => [], 'li' => ['style'], 'blockquote' => [],
        'a' => ['href'],
        'figure' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'style'], 'td' => ['colspan', 'rowspan', 'style'],
    ];

    /** Dropped together with everything inside them, not just unwrapped. */
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'noscript', 'template',
        'svg', 'math', 'form', 'input', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link',
    ];

    private const VOID = ['br', 'hr'];

    /**
     * The editors (CKEditor, Quill before it) always start the body with a
     * block tag. Plain text that merely mentions a tag ("use <b> for bold")
     * does not, so it stays text.
     */
    private const MARKUP = '/^\s*<(p|div|h[1-6]|ul|ol|blockquote|table|figure)\b[^>]*>/i';

    /** The body as safe HTML, whether it was written as HTML or as plain text. */
    public static function render(?string $body): string
    {
        $body = (string) $body;

        return self::isHtml($body) ? self::clean($body) : self::fromText($body);
    }

    public static function isHtml(string $body): bool
    {
        return (bool) preg_match(self::MARKUP, $body);
    }

    /** True when there is no text to read (an editor left empty gives "<p>&nbsp;</p>"). */
    public static function isBlank(?string $body): bool
    {
        $text = html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }

    /**
     * Validation rule for a body from the editor: an editor left empty still
     * posts "<p>&nbsp;</p>", which is no body at all.
     */
    public static function notBlankRule(string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($message) {
            if (self::isBlank(self::render((string) $value))) {
                $fail($message);
            }
        };
    }

    /** Plain text: one paragraph per non-empty line. */
    public static function fromText(string $text): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', $text)), fn (string $line) => $line !== '');

        return implode('', array_map(fn (string $line) => '<p>' . self::text($line) . '</p>', $lines));
    }

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc      = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the string as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);

        return $body ? trim(self::children($body)) : '';
    }

    private static function children(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= self::node($child);
        }

        return $html;
    }

    /** The output is built from scratch, so nothing that is not allowed can slip through. */
    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return self::text($node->nodeValue);
        }

        if (! $node instanceof DOMElement) {
            return '';   // comments, processing instructions
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        // An unknown wrapper (div, font, section…) keeps what is inside it.
        if (! array_key_exists($tag, self::ALLOWED)) {
            return self::children($node);
        }

        $attributes = self::attributes($node, $tag);

        // A link that goes nowhere safe is just its text.
        if ($tag === 'a' && $attributes === '') {
            return self::children($node);
        }

        if (in_array($tag, self::VOID, true)) {
            return "<{$tag}>";
        }

        return "<{$tag}{$attributes}>" . self::children($node) . "</{$tag}>";
    }

    private static function attributes(DOMElement $node, string $tag): string
    {
        $html = '';

        foreach (self::ALLOWED[$tag] as $name) {
            $value = trim($node->getAttribute($name));

            if ($value === '') {
                continue;
            }

            if ($name === 'href') {
                if (! preg_match('#^(https?://|mailto:|tel:)#i', $value)) {
                    continue;
                }
            } elseif ($name === 'style') {
                // Only the editor's alignment; colours, fonts and positioning are dropped.
                if (! preg_match('/text-align\s*:\s*(left|right|center|justify)/i', $value, $m)) {
                    continue;
                }
                $value = 'text-align:' . strtolower($m[1]) . ';';
            } elseif (! ctype_digit($value)) {
                continue;   // colspan / rowspan
            }

            $html .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $html;
    }

    private static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
