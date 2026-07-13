<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostFeatured;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Data\ActionResult;

final class ToggleFeaturedPostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', $post)) {
            return $result;
        }

        $post->refresh();

        if ($post->status === 'archived') {
            return ActionResult::error(
                __('Arhivirana objava ne može biti istaknuta.'),
                code: 'blog_archived_post_cannot_be_featured',
                errors: ['is_featured' => [__('Arhivirana objava ne može biti istaknuta.')]],
            );
        }

        if ($post->status !== 'published') {
            return ActionResult::error(
                __('Samo objavljena objava može biti istaknuta.'),
                code: 'blog_unpublished_post_cannot_be_featured',
                errors: ['is_featured' => [__('Samo objavljena objava može biti istaknuta.')]],
            );
        }

        $featured = false;

        $post = DB::transaction(function () use ($post, &$featured): ?Post {
            /** @var Post $post */
            $post = $post->newQuery()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($post->status !== 'published') {
                return null;
            }

            $featured = ! (bool) $post->is_featured;
            $featured ? $post->markAsFeatured() : $post->unmarkAsFeatured();

            return $post->refresh();
        });

        if (! $post) {
            return ActionResult::error(
                __('Status objave promijenjen je u međuvremenu. Osvježite popis i pokušajte ponovno.'),
                code: 'blog_post_status_changed',
                errors: ['status' => [__('Status objave promijenjen je u međuvremenu. Osvježite popis i pokušajte ponovno.')]],
            );
        }

        $featured ? PostFeatured::dispatch($post) : PostUnfeatured::dispatch($post);

        return ActionResult::success($featured ? __('Objava je istaknuta.') : __('Objava više nije istaknuta.'), $post);
    }
}
