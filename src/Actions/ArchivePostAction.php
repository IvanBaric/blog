<?php

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;

final class ArchivePostAction
{
    public function handle(Post $post): ActionResult
    {
        $post->forceFill(['status' => 'archived'])->save();

        return ActionResult::success(__('Objava je arhivirana.'), $post->refresh());
    }
}
