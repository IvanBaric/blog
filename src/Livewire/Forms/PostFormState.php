<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Forms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use IvanBaric\Blog\Models\Post;
use Livewire\Form;

final class PostFormState extends Form
{
    public array $title = ['en' => ''];

    public array $content = ['en' => ''];

    public string $status = '';

    /** @var array<int, string> */
    public array $categoryUuids = [];

    /** @var array<int, string> */
    public array $tagUuids = [];

    public ?string $featured_image = null;

    public mixed $featuredImageUpload = null;

    public ?string $published_at = null;

    public bool $is_featured = false;

    public ?int $lock_version = null;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'featuredImageUpload' => corexis_image_upload()->rules(),
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
        $this->categoryUuids = $this->taxonomyUuids($post, ['category', 'post_category'])->all();
        $this->tagUuids = $this->taxonomyUuids($post, ['tags'])->all();
        $this->featured_image = $post->featured_image;
        $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
        $this->is_featured = (bool) $post->is_featured;
        $this->lock_version = method_exists($post, 'getLockVersion') ? $post->getLockVersion() : (int) ($post->lock_version ?? 0);
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
            'published_at' => $this->publishedAtForSave(),
            'is_featured' => $this->is_featured,
            'lock_version' => $this->lock_version,
        ];
    }

    public function removeFeaturedImage(): void
    {
        $this->featuredImageUpload = null;
        $this->featured_image = null;
    }

    /**
     * @param  array<int, string>  $types
     */
    private function taxonomyUuids(Post $post, array $types): Collection
    {
        return DB::table('taxonomy_items')
            ->join('taxonomies', 'taxonomy_items.taxonomy_id', '=', 'taxonomies.id')
            ->join('taxonomyables', 'taxonomy_items.id', '=', 'taxonomyables.taxonomy_item_id')
            ->where('taxonomyables.taxonomyable_type', $post->getMorphClass())
            ->where('taxonomyables.taxonomyable_id', $post->getKey())
            ->whereIn('taxonomies.type', $types)
            ->orderBy('taxonomy_items.position')
            ->orderBy('taxonomy_items.name')
            ->pluck('taxonomy_items.uuid')
            ->filter()
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values();
    }

    private function publishedAtForSave(): ?Carbon
    {
        if ($this->published_at) {
            return Carbon::parse($this->published_at);
        }

        if ($this->status === 'published') {
            return Carbon::parse(now());
        }

        return null;
    }
}
