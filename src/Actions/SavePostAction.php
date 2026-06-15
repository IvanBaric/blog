<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Seo\Support\SeoFormDefaults;

final readonly class SavePostAction
{
    public function __construct(
        private CreatePostAction $createPostAction,
        private UpdatePostAction $updatePostAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $tagIds
     */
    public function handle(?Post $post, array $data, ?int $categoryId, array $tagIds, string $locale): ActionResult
    {
        $result = $post instanceof Post
            ? $this->updatePostAction->handle($post, $data)
            : $this->createPostAction->handle($data);

        if (! $result->successful) {
            return $result;
        }

        /** @var Post $savedPost */
        $savedPost = $result->data;

        $this->syncTaxonomies($savedPost, $categoryId, $tagIds);
        $this->syncSeo($savedPost, $data, $locale);

        return ActionResult::success($result->message, $savedPost->refresh());
    }

    /** @param  array<int, int>  $tagIds */
    private function syncTaxonomies(Post $post, ?int $categoryId, array $tagIds): void
    {
        if (! method_exists($post, 'syncTaxonomy')) {
            return;
        }

        $post->syncTaxonomy('category', $categoryId ? [(int) $categoryId] : []);
        $post->syncTaxonomy('tags', array_values(array_filter(array_map('intval', $tagIds))));
    }

    /** @param  array<string, mixed>  $data */
    private function syncSeo(Post $post, array $data, string $locale): void
    {
        $title = is_array($data['title'] ?? null) ? ($data['title'][$locale] ?? null) : null;
        $content = is_array($data['content'] ?? null) ? ($data['content'][$locale] ?? null) : null;

        $state = SeoFormDefaults::for($post)
            ->titleFrom($title)
            ->descriptionFrom($content)
            ->canonicalFrom(route('posts.show', $post))
            ->indexed($post->isPublished())
            ->resolve();

        $post->updateSeo([
            'title' => $state->title,
            'description' => $state->description,
            'canonical_url' => $state->canonicalUrl,
            'robots' => $state->robots,
            'og_image' => $data['featured_image'] ?? null,
            'twitter_image' => $data['featured_image'] ?? null,
        ]);
    }
}
