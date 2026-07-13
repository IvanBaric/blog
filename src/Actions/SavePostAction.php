<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Events\PostSaved;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Support\OptimizedMediaUpload;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final readonly class SavePostAction
{
    public function __construct(
        private CreatePostAction $createPostAction,
        private UpdatePostAction $updatePostAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  int|string|array<int, int|string>|null  $categoryId
     * @param  array<int, int|string>  $tagIds
     */
    public function handle(?Post $post, array $data, int|string|array|null $categoryId, array $tagIds, string $locale): ActionResult
    {
        if (! preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/', $locale)) {
            return ActionResult::error(
                __('Odabrani jezik nije valjan.'),
                code: 'blog_locale_invalid',
                errors: ['locale' => [__('Odabrani jezik nije valjan.')]],
            );
        }

        $featuredImageUpload = $data['_featured_image_upload'] ?? null;
        $removeFeaturedImage = (bool) ($data['_remove_featured_image'] ?? false);
        unset($data['_featured_image_upload'], $data['_remove_featured_image']);

        $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
        $categoryIds = array_values(array_filter($categoryIds, static fn (mixed $id): bool => filled($id)));

        $taxonomyTablesExist = $this->taxonomyTablesExist();
        $resolvedCategoryIds = $taxonomyTablesExist
            ? $this->resolveTaxonomyItemIds('category', $categoryIds)
            : array_values(array_filter(array_map('intval', $categoryIds)));
        $resolvedCategoryId = $resolvedCategoryIds[0] ?? null;
        $resolvedTagIds = $taxonomyTablesExist
            ? $this->resolveTaxonomyItemIds('tags', $tagIds)
            : array_values(array_filter(array_map('intval', $tagIds)));

        if ($taxonomyTablesExist && count($resolvedCategoryIds) !== count($categoryIds)) {
            return ActionResult::error(
                message: __('Jedna ili više odabranih kategorija nije dostupna.'),
                code: 'blog_category_unavailable',
                errors: ['category' => [__('Jedna ili više odabranih kategorija nije dostupna.')]],
            );
        }

        if ($taxonomyTablesExist && count($resolvedTagIds) !== count(array_values(array_filter($tagIds, static fn (mixed $tagId): bool => filled($tagId))))) {
            return ActionResult::error(
                message: __('Jedna ili više odabranih oznaka nije dostupna.'),
                code: 'blog_tags_unavailable',
                errors: ['tags' => [__('Jedna ili više odabranih oznaka nije dostupna.')]],
            );
        }

        $result = $post instanceof Post
            ? $this->updatePostAction->handle($post, $data)
            : $this->createPostAction->handle($data);

        if (! $result->success) {
            return $result;
        }

        /** @var Post $savedPost */
        $savedPost = $result->data->refresh();

        $this->syncFeaturedImage($savedPost, $featuredImageUpload, $removeFeaturedImage);
        $savedPost->refresh();

        $savedPost->syncTaxonomy('category', $resolvedCategoryIds);
        $savedPost->syncTaxonomy('tags', $resolvedTagIds);

        PostSaved::dispatch(
            post: $savedPost,
            categoryId: $resolvedCategoryId,
            tagIds: $resolvedTagIds,
            locale: $locale,
            data: $data,
        );

        return ActionResult::success($result->message, $savedPost);
    }

    private function syncFeaturedImage(Post $post, mixed $upload, bool $removeFeaturedImage): void
    {
        if (! $removeFeaturedImage && ! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        $collection = Post::FEATURED_IMAGE_COLLECTION;
        $gallery = $post->gallery($collection);

        if ($removeFeaturedImage && ! $upload instanceof TemporaryUploadedFile) {
            if ($gallery) {
                $gallery->clearMediaCollection($collection);
                $gallery->delete();
            }

            return;
        }

        $title = $post->localized('title') ?: __('Naslovna slika objave');
        $gallery = $post->getOrCreateGallery($collection, ['title' => $title]);
        $gallery->clearMediaCollection($collection);

        $media = app(OptimizedMediaUpload::class)
            ->addUploadToGallery($gallery, $upload, $collection, pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: $upload->hashName(), [
                'alt' => $title,
                'title' => $title,
                'caption' => '',
                'description' => '',
                'credit' => '',
                'source_url' => '',
                'license' => '',
                'is_decorative' => false,
            ]);

        $gallery->forceFill([
            'title' => $title,
            'featured_media_id' => $media->id,
        ])->save();
    }

    private function taxonomyTablesExist(): bool
    {
        $taxonomyItemClass = TaxonomyModels::taxonomyItem();
        $taxonomyItem = new $taxonomyItemClass;

        return Schema::hasTable($taxonomyItem->getTable());
    }

    /**
     * @param  array<int, int|string>  $idsOrUuids
     * @return array<int, int>
     */
    private function resolveTaxonomyItemIds(string $type, array $idsOrUuids): array
    {
        $references = array_values(array_unique(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            array_filter($idsOrUuids, static fn (mixed $reference): bool => filled($reference)),
        )));

        if ($references === []) {
            return [];
        }

        $numericIds = array_values(array_map('intval', array_filter($references, 'is_numeric')));
        $uuids = array_values(array_filter($references, static fn (string $reference): bool => ! is_numeric($reference)));
        $taxonomyItemClass = TaxonomyModels::taxonomyItem();
        $query = $taxonomyItemClass::query()->forType($type);

        if (config('corexis.tenancy.enabled', false)) {
            $query->where((string) config('corexis.tenancy.id_column', 'team_id'), corexis_tenant_id());
        }

        $items = $query
            ->where(function ($query) use ($numericIds, $uuids): void {
                $query
                    ->when($numericIds !== [], fn ($query) => $query->whereIn('id', $numericIds))
                    ->when($uuids !== [], fn ($query) => $query->orWhereIn('uuid', $uuids));
            })
            ->get(['id', 'uuid']);

        $idsById = $items->mapWithKeys(static fn ($item): array => [
            (int) $item->getKey() => (int) $item->getKey(),
        ])->all();
        $idsByUuid = $items->mapWithKeys(static fn ($item): array => [
            (string) $item->getAttribute('uuid') => (int) $item->getKey(),
        ])->all();

        return array_values(array_filter(array_map(
            static fn (string $reference): ?int => is_numeric($reference)
                ? ($idsById[(int) $reference] ?? null)
                : ($idsByUuid[$reference] ?? null),
            $references,
        )));
    }
}
