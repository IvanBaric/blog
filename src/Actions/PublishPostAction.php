<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostPublished;
use IvanBaric\Blog\Events\PostUnpublished;
use IvanBaric\Blog\Models\Post;

final class PublishPostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post, bool $published = true): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.publish', $post)) {
            return $result;
        }

        $post = DB::transaction(function () use ($post, $published): Post {
            /** @var Post $post */
            $post = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $published ? $post->publish() : $post->unpublish();

            return $post->refresh();
        });

        $published ? PostPublished::dispatch($post) : PostUnpublished::dispatch($post);

        return ActionResult::success($published ? __('Objava je objavljena.') : __('Objava je vraćena u skicu.'), $post);
    }
}
