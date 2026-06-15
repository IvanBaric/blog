<?php

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;

final class ToggleFeaturedPostAction
{
    public function handle(Post $post): ActionResult
    {
        $post->is_featured ? $post->unmarkAsFeatured() : $post->markAsFeatured();

        return ActionResult::success($post->is_featured ? __('Objava je istaknuta.') : __('Objava više nije istaknuta.'), $post->refresh());
    }
}
