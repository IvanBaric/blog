<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Events\PostCreated;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Blog\Support\PublishablePostContent;
use IvanBaric\Corexis\Contracts\TenantResolver;
use IvanBaric\Corexis\Data\ActionResult;

final class CreatePostAction
{
    use AuthorizesBlogActions;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.create')) {
            return $result;
        }

        $validator = Validator::make($data, $this->rules(), attributes: $this->attributes());

        $validator->after(function ($validator) use ($data): void {
            if (($data['status'] ?? null) === 'archived') {
                $validator->errors()->add('status', __('Nova objava ne može biti izrađena kao arhivirana.'));
            }

            if (($data['status'] ?? null) === 'published' && ! PublishablePostContent::isPresent($data['content'] ?? null)) {
                $validator->errors()->add('content', __('Objavu nije moguće objaviti bez sadržaja.'));
            }
        });

        if ($validator->fails()) {
            return ActionResult::error(
                message: $validator->errors()->first('content') ?: __('Objava nije mogla biti izrađena.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();

        $tenantResolver = app(TenantResolver::class);
        $tenantId = $tenantResolver->id();

        if ($tenantResolver->enabled() && $tenantId === null) {
            return ActionResult::error(
                __('Nije moguće odrediti organizaciju za novu objavu.'),
                code: 'blog_tenant_unresolved',
                errors: ['authorization' => [__('Nije moguće odrediti organizaciju za novu objavu.')]],
            );
        }

        $actorId = corexis_actor_id();

        if ($actorId !== null) {
            $validated['user_id'] = $actorId;
            $validated['updated_user_id'] = $actorId;
        }

        if (($validated['status'] ?? null) !== 'published') {
            $validated['is_featured'] = false;
        }

        $model = BlogModels::post();
        $post = $model::query()->create($validated);

        PostCreated::dispatch($post);

        return ActionResult::success(__('Objava je izrađena.'), $post);
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
