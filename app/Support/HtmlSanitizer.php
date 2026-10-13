<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist sanitizer for rich-text docs (the output of the Trix editor).
 * Anything not explicitly allowed is removed; unknown tags are unwrapped so their text survives.
 */
class HtmlSanitizer
{
    protected const ALLOWED = [
        'div' => [], 'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'del' => [], 's' => [],
        'h1' => [], 'h2' => [], 'h3' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href'],
        'img' => ['src', 'alt', 'width', 'height'],
        'figure' => ['class', 'data-trix-attachment', 'data-trix-content-type', 'data-trix-attributes'],
        'figcaption' => ['class'],
        'span' => ['class'],
    ];

    /** Removed together with everything inside them. */
    protected const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'noscript', 'frame', 'frameset'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }

        static::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    protected static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                static::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            static::cleanAttributes($child, self::ALLOWED[$tag]);
            static::walk($child);
        }
    }

    protected static function cleanAttributes(DOMElement $el, array $allowed): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);

            $keep = in_array($name, $allowed, true) && match ($name) {
                'href' => static::safeUrl($value, true),
                'src' => static::safeUrl($value, false),
                'class' => (bool) preg_match('/^[a-z0-9_\- ]{0,200}$/i', $value),
                'width', 'height' => ctype_digit($value),
                default => true,
            };

            if (! $keep) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($el->tagName === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
            $el->setAttribute('target', '_blank');
        }
    }

    protected static function safeUrl(string $url, bool $allowMail): bool
    {
        // Strip control/whitespace characters browsers ignore (e.g. "java\tscript:").
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $url));

        if (str_starts_with($normalized, '//') || str_starts_with($normalized, '/\\')) {
            return false;
        }
        if (str_starts_with($normalized, '/') || str_starts_with($normalized, '#')) {
            return true;
        }
        if (str_starts_with($normalized, 'https://') || str_starts_with($normalized, 'http://')) {
            return true;
        }

        return $allowMail && str_starts_with($normalized, 'mailto:');
    }

    /** Plain text version (for search snippets and previews). */
    public static function text(?string $html, int $limit = 0): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/div|\/p|\/li|\/h[1-3])[^>]*>/i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $limit ? \Illuminate\Support\Str::limit($text, $limit) : $text;
    }
}
