<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostSaved;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

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
        $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
        $categoryIds = array_values(array_filter($categoryIds, static fn (mixed $id): bool => filled($id)));

        $resolvedCategoryIds = $this->taxonomyTablesExist()
            ? $this->resolveTaxonomyItemIds('category', $categoryIds)
            : array_values(array_filter(array_map('intval', $categoryIds)));
        $resolvedCategoryId = $resolvedCategoryIds[0] ?? null;
        $resolvedTagIds = $this->taxonomyTablesExist()
            ? $this->resolveTaxonomyItemIds('tags', $tagIds)
            : array_values(array_filter(array_map('intval', $tagIds)));

        if ($this->taxonomyTablesExist() && count($resolvedCategoryIds) !== count($categoryIds)) {
            return ActionResult::failure(
                message: __('Jedna ili više odabranih kategorija nije dostupna.'),
                code: 'blog_category_unavailable',
                errors: ['category' => [__('Jedna ili više odabranih kategorija nije dostupna.')]],
            );
        }

        if ($this->taxonomyTablesExist() && count($resolvedTagIds) !== count(array_values(array_filter($tagIds, static fn (mixed $tagId): bool => filled($tagId))))) {
            return ActionResult::failure(
                message: __('Jedna ili više odabranih oznaka nije dostupna.'),
                code: 'blog_tags_unavailable',
                errors: ['tags' => [__('Jedna ili više odabranih oznaka nije dostupna.')]],
            );
        }

        $result = $post instanceof Post
            ? $this->updatePostAction->handle($post, $data)
            : $this->createPostAction->handle($data);

        if (! $result->successful) {
            return $result;
        }

        /** @var Post $savedPost */
        $savedPost = $result->data->refresh();

        if (method_exists($savedPost, 'syncTaxonomy')) {
            $savedPost->syncTaxonomy('category', $resolvedCategoryIds);
            $savedPost->syncTaxonomy('tags', $resolvedTagIds);
        }

        PostSaved::dispatch(
            post: $savedPost,
            categoryId: $resolvedCategoryId,
            tagIds: $resolvedTagIds,
            locale: $locale,
            data: $data,
        );

        return ActionResult::success($result->message, $savedPost);
    }

    private function resolveTaxonomyItemId(string $type, int|string|null $idOrUuid): ?int
    {
        if ($idOrUuid === null || $idOrUuid === '') {
            return null;
        }

        $query = TaxonomyItem::query()->forType($type);

        if (is_int($idOrUuid)) {
            $id = (clone $query)->whereKey($idOrUuid)->value('id');

            return $id === null ? null : (int) $id;
        }

        $idOrUuid = trim($idOrUuid);

        if ($idOrUuid === '') {
            return null;
        }

        $id = (clone $query)
            ->where(is_numeric($idOrUuid) ? 'id' : 'uuid', $idOrUuid)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function fallbackTaxonomyId(int|string|null $idOrUuid): ?int
    {
        if ($idOrUuid === null || $idOrUuid === '') {
            return null;
        }

        return is_numeric($idOrUuid) ? (int) $idOrUuid : null;
    }

    private function taxonomyTablesExist(): bool
    {
        $taxonomyItem = new TaxonomyItem;

        return Schema::hasTable($taxonomyItem->getTable());
    }

    /**
     * @param  array<int, int|string>  $idsOrUuids
     * @return array<int, int>
     */
    private function resolveTaxonomyItemIds(string $type, array $idsOrUuids): array
    {
        $ids = [];

        foreach (array_values(array_unique(array_filter($idsOrUuids, static fn (mixed $id): bool => filled($id)))) as $idOrUuid) {
            $resolved = $this->resolveTaxonomyItemId($type, is_int($idOrUuid) ? $idOrUuid : (string) $idOrUuid);

            if ($resolved !== null) {
                $ids[] = (int) $resolved;
            }
        }

        return array_values(array_unique($ids));
    }
}
