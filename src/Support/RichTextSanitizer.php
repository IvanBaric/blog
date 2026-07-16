<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Support;

use IvanBaric\Corexis\Support\RichTextSanitizer as CorexisRichTextSanitizer;

final class RichTextSanitizer
{
    public function __construct(private readonly CorexisRichTextSanitizer $sanitizer) {}

    public function sanitize(string $content): string
    {
        return $this->sanitizer->sanitize($content);
    }
}
