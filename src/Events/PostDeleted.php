<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;

final readonly class PostDeleted implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int|string $postKey,
        public string $uuid,
    ) {}
}
