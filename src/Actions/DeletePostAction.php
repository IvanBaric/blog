<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostDeleted;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Data\ActionResult;

final class DeletePostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.delete', $post)) {
            return $result;
        }

        $deletedPost = DB::transaction(function () use ($post): ?array {
            /** @var Post $currentPost */
            $currentPost = $post->newQuery()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($currentPost->status, ['draft', 'archived'], true)) {
                return null;
            }

            $deletedPost = [
                'key' => $currentPost->getKey(),
                'uuid' => (string) $currentPost->uuid,
            ];

            $currentPost->delete();

            return $deletedPost;
        });

        if ($deletedPost === null) {
            return ActionResult::error(
                __('Objavljenu objavu prvo arhivirajte prije brisanja.'),
                code: 'blog_published_post_cannot_be_deleted',
                errors: ['status' => [__('Objavljenu objavu prvo arhivirajte prije brisanja.')]],
            );
        }

        PostDeleted::dispatch($deletedPost['key'], $deletedPost['uuid']);

        return ActionResult::success(__('Objava je obrisana.'));
    }
}
