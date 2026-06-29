<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostCreated;
use IvanBaric\Blog\Models\Post;

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

        if ($validator->fails()) {
            return ActionResult::failure(__('Objava nije mogla biti izrađena.'), $validator->errors());
        }

        $model = config('blog.models.post', Post::class);
        $post = DB::transaction(
            static fn (): Post => $model::query()->create($validator->validated()),
        );

        PostCreated::dispatch($post);

        return ActionResult::success(__('Objava je izrađena.'), $post);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'team_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'title' => ['required', 'array'],
            'excerpt' => ['nullable', 'array'],
            'content' => ['nullable', 'array'],
            'context' => ['nullable', 'string', Rule::in(array_keys(config('blog.contexts', [])))],
            'status' => ['required', 'string', Rule::in(array_keys(config('blog.statuses', [])))],
            'featured_image' => ['nullable', 'string', 'max:2048'],
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
            'user_id' => __('autor'),
            'published_at' => __('datum objave'),
            'starts_at' => __('datum početka'),
            'ends_at' => __('datum završetka'),
            'location' => __('lokacija'),
            'sort_order' => __('redoslijed'),
            'is_featured' => __('istaknuto'),
        ];
    }
}
