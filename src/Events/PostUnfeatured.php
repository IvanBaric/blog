<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;

final readonly class PostUnfeatured implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Post $post,
    ) {}
}
