<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostUpdated;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\PublishablePostContent;
use IvanBaric\Corexis\Concerns\UsesOptimisticLocking;
use IvanBaric\Corexis\Data\ActionResult;

final class UpdatePostAction
{
    use AuthorizesBlogActions, UsesOptimisticLocking;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Post $post, array $data): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', $post)) {
            return $result;
        }

        $post = $post->newQuery()
            ->whereKey($post->getKey())
            ->firstOrFail();

        if ($post->status === 'archived') {
            return ActionResult::error(
                __('Arhivirana objava je zaključana. Prvo je vratite u skicu.'),
                code: 'blog_archived_post_is_locked',
                errors: ['status' => [__('Arhivirana objava je zaključana. Prvo je vratite u skicu.')]],
            );
        }

        $validator = Validator::make($data, $this->rules(), attributes: $this->attributes());

        $validator->after(function ($validator) use ($data): void {
            if (($data['status'] ?? null) === 'archived') {
                $validator->errors()->add('status', __('Objavu arhivirajte kroz akciju Arhiviraj.'));
            }

            if (($data['status'] ?? null) === 'published' && ! PublishablePostContent::isPresent($data['content'] ?? null)) {
                $validator->errors()->add('content', __('Objavu nije moguće objaviti bez sadržaja.'));
            }
        });

        if ($validator->fails()) {
            return ActionResult::error(
                message: $validator->errors()->first('content') ?: __('Objava nije mogla biti ažurirana.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();

        $actorId = corexis_actor_id();

        if ($actorId !== null) {
            $validated['updated_user_id'] = $actorId;
        }

        if (($validated['status'] ?? null) !== 'published') {
            $validated['is_featured'] = false;
        }

        $expectedLockVersion = $this->pullExpectedLockVersion($validated);

        $saved = DB::transaction(function () use ($post, $validated, $expectedLockVersion): bool {
            return $this->saveWithOptimisticLock($post, $validated, $expectedLockVersion);
        });

        if (! $saved) {
            return $this->staleModelResult();
        }

        $post->refresh();

        PostUpdated::dispatch($post);

        return ActionResult::success(__('Objava je ažurirana.'), $post);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'excerpt' => ['nullable', 'array'],
            'content' => ['nullable', 'array'],
            'context' => ['nullable', 'string', Rule::in(array_keys(config('blog.contexts', [])))],
            'status' => ['required', 'string', Rule::in(array_keys(config('blog.statuses', [])))],
            'published_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'title' => __('naslov'),
            'excerpt' => __('sažetak'),
            'content' => __('sadržaj'),
            'context' => __('kontekst'),
            'status' => __('status'),
            'published_at' => __('datum objave'),
            'starts_at' => __('datum početka'),
            'ends_at' => __('datum završetka'),
            'location' => __('lokacija'),
            'sort_order' => __('redoslijed'),
            'is_featured' => __('istaknuto'),
        ];
    }
}
