<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Data;

use IvanBaric\Corexis\Data\ActionResult as CorexisActionResult;

final readonly class ActionResult
{
    public function __construct(
        public bool $successful,
        public string $message,
        public mixed $data = null,
        public ?string $code = null,
        /** @var array<string, mixed> */
        public array $errors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function success(string $message, mixed $data = null, ?string $code = null, array $errors = []): self
    {
        return new self(true, $message, $data, $code, $errors);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function failure(string $message, mixed $data = null, ?string $code = null, array $errors = []): self
    {
        return new self(false, $message, $data, $code, $errors);
    }

    public function toCorexis(): CorexisActionResult
    {
        return new CorexisActionResult(
            success: $this->successful,
            message: $this->message,
            data: $this->data,
            errors: $this->errors,
            code: $this->code,
        );
    }

    public static function fromCorexis(CorexisActionResult $result): self
    {
        return new self(
            successful: $result->success,
            message: $result->message,
            data: $result->data,
            code: $result->code,
            errors: $result->errors,
        );
    }
}
