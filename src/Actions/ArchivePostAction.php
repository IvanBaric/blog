<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostArchived;
use IvanBaric\Blog\Models\Post;

final class ArchivePostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', $post)) {
            return $result;
        }

        $post = DB::transaction(function () use ($post): Post {
            $post->forceFill(['status' => 'archived'])->save();

            return $post->refresh();
        });

        PostArchived::dispatch($post);

        return ActionResult::success(__('Objava je arhivirana.'), $post);
    }
}
