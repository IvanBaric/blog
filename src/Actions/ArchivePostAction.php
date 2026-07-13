<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostArchived;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Data\ActionResult;

final class ArchivePostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', $post)) {
            return $result;
        }

        $wasFeatured = (bool) $post->is_featured;

        $archiverId = corexis_actor_id();

        $post = DB::transaction(function () use ($post, $archiverId): Post {
            /** @var Post $post */
            $post = $post->newQuery()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $post->archive($archiverId);

            return $post->refresh();
        });

        PostArchived::dispatch($post);

        if ($wasFeatured) {
            PostUnfeatured::dispatch($post);
        }

        return ActionResult::success(__('Objava je arhivirana.'), $post);
    }
}
