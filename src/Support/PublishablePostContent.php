<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Support;

final class PublishablePostContent
{
    public static function isPresent(mixed $content): bool
    {
        if (is_array($content)) {
            foreach ($content as $value) {
                if (self::isPresent($value)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_scalar($content)) {
            return false;
        }

        $text = html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text) ?? '';

        return $text !== '';
    }
}
