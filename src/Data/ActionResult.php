<?php

namespace IvanBaric\Blog\Data;

final readonly class ActionResult
{
    public function __construct(
        public bool $successful,
        public string $message,
        public mixed $data = null,
    ) {}

    public static function success(string $message, mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }
}
