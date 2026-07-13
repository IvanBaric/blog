<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Forms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use Livewire\Attributes\Locked;
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

    public mixed $featuredImageUpload = null;

    public bool $removeFeaturedImage = false;

    public ?string $published_at = null;

    public bool $is_featured = false;

    #[Locked]
    public ?int $lock_version = null;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'featuredImageUpload' => corexis_image_upload()->rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_replace(
            corexis_image_upload()->messages('featuredImageUpload'),
            corexis_image_upload()->messages('form.featuredImageUpload'),
        );
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
        $this->featuredImageUpload = null;
        $this->removeFeaturedImage = false;
        $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
        $this->is_featured = (bool) $post->is_featured;
        $this->lock_version = $post->getLockVersion();
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $this->validate();

        return [
            'title' => $this->title,
            'content' => array_filter($this->content) === [] ? null : $this->content,
            'status' => $this->status,
            'published_at' => $this->publishedAtForSave(),
            'is_featured' => $this->is_featured,
            'lock_version' => $this->lock_version,
            '_featured_image_upload' => $this->featuredImageUpload,
            '_remove_featured_image' => $this->removeFeaturedImage && ! $this->featuredImageUpload,
        ];
    }

    public function removeFeaturedImage(): void
    {
        $this->featuredImageUpload = null;
        $this->removeFeaturedImage = true;
    }

    /**
     * @param  array<int, string>  $types
     */
    private function taxonomyUuids(Post $post, array $types): Collection
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $taxonomyModel = TaxonomyModels::taxonomy();
        $taxonomyItemsTable = (new $taxonomyItemModel)->getTable();
        $taxonomiesTable = (new $taxonomyModel)->getTable();
        $query = DB::table($taxonomyItemsTable)
            ->join($taxonomiesTable, $taxonomyItemsTable.'.taxonomy_id', '=', $taxonomiesTable.'.id')
            ->join('taxonomyables', $taxonomyItemsTable.'.id', '=', 'taxonomyables.taxonomy_item_id')
            ->where('taxonomyables.taxonomyable_type', $post->getMorphClass())
            ->where('taxonomyables.taxonomyable_id', $post->getKey())
            ->whereIn($taxonomiesTable.'.type', $types);

        if (config('corexis.tenancy.enabled', false)) {
            $column = (string) config('corexis.tenancy.id_column', 'team_id');
            $teamId = $post->getAttribute($column);

            if (Schema::hasColumn($taxonomyItemsTable, $column)) {
                $query->where($taxonomyItemsTable.'.'.$column, $teamId);
            }

            if (Schema::hasColumn('taxonomyables', $column)) {
                $query->where('taxonomyables.'.$column, $teamId);
            }
        }

        return $query
            ->orderBy($taxonomyItemsTable.'.position')
            ->orderBy($taxonomyItemsTable.'.name')
            ->pluck($taxonomyItemsTable.'.uuid')
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
