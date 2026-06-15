<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Forms;

use Illuminate\Support\Carbon;
use IvanBaric\Blog\Models\Post;
use Livewire\Form;

final class PostFormState extends Form
{
    public array $title = ['en' => ''];

    public array $content = ['en' => ''];

    public string $status = '';

    public ?int $categoryId = null;

    /** @var array<int, int> */
    public array $tagIds = [];

    public ?string $featured_image = null;

    public mixed $featuredImageUpload = null;

    public ?string $published_at = null;

    public bool $is_featured = false;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'featuredImageUpload' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function initialize(string $locale): void
    {
        $this->title = [$locale => ''];
        $this->content = [$locale => ''];
        $this->status = config('blog.default_status', 'draft');
    }

    public function fillFromPost(Post $post, string $locale): void
    {
        $this->title = is_array($post->title) ? $post->title : [$locale => (string) $post->title];
        $this->content = is_array($post->content) ? $post->content : [$locale => (string) $post->content];
        $this->status = $post->status;
        $this->categoryId = $post->taxonomy('category')->value('taxonomy_items.id');
        $this->tagIds = $post->taxonomy('tags')
            ->pluck('taxonomy_items.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $this->featured_image = $post->featured_image;
        $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
        $this->is_featured = (bool) $post->is_featured;
    }

    /** @return array<string, mixed> */
    public function data(?int $teamId): array
    {
        $this->validate();

        $featuredImage = $this->featured_image;

        if ($this->featuredImageUpload) {
            $featuredImage = $this->featuredImageUpload->store('blog', 'public');
            $this->featured_image = $featuredImage;
            $this->featuredImageUpload = null;
        }

        return [
            'team_id' => $teamId,
            'title' => $this->title,
            'content' => array_filter($this->content) === [] ? null : $this->content,
            'status' => $this->status,
            'featured_image' => $featuredImage,
            'published_at' => $this->published_at ? Carbon::parse($this->published_at) : null,
            'is_featured' => $this->is_featured,
        ];
    }

    public function removeFeaturedImage(): void
    {
        $this->featuredImageUpload = null;
        $this->featured_image = null;
    }
}
