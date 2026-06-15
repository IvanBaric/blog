<?php

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;

final class PublishPostAction
{
    public function handle(Post $post, bool $published = true): ActionResult
    {
        $published ? $post->publish() : $post->unpublish();

        return ActionResult::success($published ? __('Objava je objavljena.') : __('Objava je vraćena u skicu.'), $post->refresh());
    }
}
