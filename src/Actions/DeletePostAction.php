<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostDeleted;
use IvanBaric\Blog\Models\Post;

final class DeletePostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.delete', $post)) {
            return $result;
        }

        $postKey = $post->getKey();
        $uuid = (string) $post->uuid;

        DB::transaction(static function () use ($post): void {
            $post->delete();
        });

        PostDeleted::dispatch($postKey, $uuid);

        return ActionResult::success(__('Objava je obrisana.'));
    }
}
