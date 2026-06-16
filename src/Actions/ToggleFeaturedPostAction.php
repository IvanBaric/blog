<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostFeatured;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Models\Post;

final class ToggleFeaturedPostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', $post)) {
            return $result;
        }

        $featured = ! (bool) $post->is_featured;
        $post = DB::transaction(function () use ($post, $featured): Post {
            $featured ? $post->markAsFeatured() : $post->unmarkAsFeatured();

            return $post->refresh();
        });

        $featured ? PostFeatured::dispatch($post) : PostUnfeatured::dispatch($post);

        return ActionResult::success($featured ? __('Objava je istaknuta.') : __('Objava više nije istaknuta.'), $post);
    }
}
