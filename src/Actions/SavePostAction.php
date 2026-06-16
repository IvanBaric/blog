<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostSaved;
use IvanBaric\Blog\Models\Post;

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
        $savedPost = $result->data->refresh();

        PostSaved::dispatch(
            post: $savedPost,
            categoryId: $categoryId,
            tagIds: array_values(array_filter(array_map('intval', $tagIds))),
            locale: $locale,
            data: $data,
        );

        return ActionResult::success($result->message, $savedPost);
    }
}
