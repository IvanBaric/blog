<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostPublished;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Events\PostUnpublished;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\PublishablePostContent;
use IvanBaric\Corexis\Data\ActionResult;

final class PublishPostAction
{
    use AuthorizesBlogActions;

    public function handle(Post $post, bool $published = true): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.publish', $post)) {
            return $result;
        }

        $post->refresh();

        if ($post->status === 'archived' && $published) {
            return ActionResult::error(
                __('Arhiviranu objavu prvo vratite u skicu.'),
                code: 'blog_archived_post_cannot_be_published',
                errors: ['status' => [__('Arhiviranu objavu prvo vratite u skicu.')]],
            );
        }

        $wasFeatured = false;

        $post = DB::transaction(function () use ($post, $published, &$wasFeatured): ?Post {
            /** @var Post $post */
            $post = $post->newQuery()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($published && ! PublishablePostContent::isPresent($post->content)) {
                return null;
            }

            $wasFeatured = ! $published && (bool) $post->is_featured;

            $published ? $post->publish() : $post->unpublish();

            return $post->refresh();
        });

        if (! $post) {
            return ActionResult::error(
                __('Objavu nije moguće objaviti bez sadržaja.'),
                code: 'blog_post_content_required_for_publish',
                errors: ['content' => [__('Objavu nije moguće objaviti bez sadržaja.')]],
            );
        }

        $published ? PostPublished::dispatch($post) : PostUnpublished::dispatch($post);

        if ($wasFeatured) {
            PostUnfeatured::dispatch($post);
        }

        return ActionResult::success($published ? __('Objava je objavljena.') : __('Objava je vraćena u skicu.'), $post);
    }
}
