<?php

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;

final class DeletePostAction
{
    public function handle(Post $post): ActionResult
    {
        $post->delete();

        return ActionResult::success(__('Objava je obrisana.'));
    }
}
