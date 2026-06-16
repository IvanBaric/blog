<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;

final readonly class PostSaved implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $tagIds
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public Post $post,
        public ?int $categoryId,
        public array $tagIds,
        public string $locale,
        public array $data = [],
    ) {}
}
