<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichTextSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h2', 'h3', 'blockquote', 'code',
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template',
    ];

    public function sanitize(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        if (! str_contains($content, '<')) {
            return $this->plainText($content);
        }

        if (! class_exists(DOMDocument::class)) {
            return $this->safeFallback($content);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div data-blog-rich-text-root>'.$content.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $this->safeFallback($content);
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return $this->safeFallback($content);
        }

        $this->sanitizeChildren($root);

        $html = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $document->saveHTML($child) ?: '';
        }

        return preg_replace('/<p>\s*(?:&nbsp;|\s|<br\s*\/?>)*<\/p>/i', '', $html) ?? $html;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                if ($node->nodeType === XML_COMMENT_NODE) {
                    $parent->removeChild($node);
                }

                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($node);

                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);
            $this->sanitizeChildren($node);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if ($tag !== 'a' || ! in_array(strtolower($attribute->name), ['href', 'title', 'target', 'rel'], true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag !== 'a') {
            return;
        }

        $href = trim(html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (! $this->safeHref($href)) {
            $element->removeAttribute('href');
        }

        if ($element->getAttribute('target') !== '_blank') {
            $element->removeAttribute('target');
            $element->removeAttribute('rel');

            return;
        }

        $element->setAttribute('rel', 'noopener noreferrer');
    }

    private function safeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x1F\x7F]/u', $href)) {
            return false;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/') || str_starts_with($href, './') || str_starts_with($href, '../')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    private function plainText(string $content): string
    {
        $paragraphs = preg_split('/\R{2,}/', $content) ?: [];

        return collect($paragraphs)
            ->map(static fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->map(static fn (string $paragraph): string => '<p>'.nl2br(e($paragraph)).'</p>')
            ->implode('');
    }

    private function safeFallback(string $content): string
    {
        $content = strip_tags($content, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h2><h3><blockquote><code>');

        return preg_replace_callback(
            '/<([a-z0-9]+)\b[^>]*>/i',
            static fn (array $matches): string => '<'.strtolower($matches[1]).'>',
            $content,
        ) ?? '';
    }
}
