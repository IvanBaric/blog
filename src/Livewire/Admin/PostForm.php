<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanBaric\Blog\Actions\SavePostAction;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Livewire\Forms\PostFormState;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\TeamResolver;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

final class PostForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?Post $post = null;

    public string $locale = 'en';

    public PostFormState $form;

    public string $categorySearch = '';

    public string $newCategoryName = '';

    public string $tagSearch = '';

    public string $newTagName = '';

    public function mount(?Post $post = null): void
    {
        $this->locale = app()->getLocale();
        $this->form->initialize($this->locale);

        if ($post?->exists) {
            $this->post = $post;
            abort_unless((int) $post->getAttribute('team_id') === $this->currentTeamId(), 404);
            $this->form->fillFromPost($post, $this->locale);
        }
    }

    public function save(SavePostAction $savePostAction): void
    {
        try {
            $data = $this->form->data($this->currentTeamId());
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::failure(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $post = $this->post?->exists ? $this->postForCurrentTeam((string) $this->post->uuid) : null;
        $result = $savePostAction->handle(
            post: $post,
            data: $data,
            categoryId: $this->form->categoryId,
            tagIds: $this->form->tagIds,
            locale: $this->locale,
        );

        if (! $result->successful) {
            foreach ($result->data?->messages() ?? [] as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            $this->toastFromResult($result);

            return;
        }

        $this->post = $result->data;
        $this->form->fillFromPost($this->post, $this->locale);

        $this->toastFromResult($result);
        $this->redirectRoute(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $this->post->uuid], navigate: true);
    }

    public function createCategory(): void
    {
        $category = $this->createTaxonomyItem('category', $this->newCategoryName, __('Categories'), false);

        $this->form->categoryId = (int) $category->id;
        $this->categorySearch = '';
        $this->newCategoryName = '';
    }

    public function createTag(): void
    {
        $tag = $this->createTaxonomyItem('tags', $this->newTagName, __('Tags'), true);
        $tagId = (int) $tag->id;

        if (! in_array($tagId, $this->form->tagIds, true)) {
            $this->form->tagIds[] = $tagId;
        }

        $this->tagSearch = '';
        $this->newTagName = '';
    }

    public function removeFeaturedImage(): void
    {
        $this->form->removeFeaturedImage();
    }

    public function removeTag(int $tagId): void
    {
        $this->form->tagIds = array_values(array_filter(
            $this->form->tagIds,
            static fn (int $selectedTagId): bool => $selectedTagId !== $tagId,
        ));
    }

    #[Computed]
    public function categories(): Collection
    {
        return $this->taxonomyItems('category', $this->categorySearch, $this->form->categoryId ? [(int) $this->form->categoryId] : []);
    }

    #[Computed]
    public function tags(): Collection
    {
        return $this->taxonomyItems('tags', $this->tagSearch, $this->form->tagIds);
    }

    #[Computed]
    public function selectedTags(): Collection
    {
        if ($this->form->tagIds === []) {
            return collect();
        }

        return TaxonomyItem::query()
            ->whereIn('id', $this->form->tagIds)
            ->ordered()
            ->get();
    }

    public function render()
    {
        return view('blog::livewire.admin.post-form')
            ->layout(config('blog.admin_ui.layout', 'layouts.app'), [
                'title' => $this->post?->exists ? __('Uredi objavu') : __('Nova objava'),
            ]);
    }

    public function featuredImageUrl(): ?string
    {
        if (! $this->form->featured_image) {
            return null;
        }

        if (str_starts_with($this->form->featured_image, 'http://') || str_starts_with($this->form->featured_image, 'https://')) {
            return $this->form->featured_image;
        }

        return Storage::disk('public')->url($this->form->featured_image);
    }

    private function createTaxonomyItem(string $type, string $name, string $taxonomyName, bool $multiple): TaxonomyItem
    {
        $name = trim($name);

        if ($name === '') {
            $this->addError($type === 'category' ? 'newCategoryName' : 'newTagName', __('Naziv je obavezan.'));

            return new TaxonomyItem;
        }

        $taxonomy = Taxonomy::query()->firstOrCreate(
            ['type' => $type, 'slug' => Str::slug($taxonomyName)],
            ['name' => $taxonomyName, 'is_filterable' => true, 'is_multiple' => $multiple],
        );

        return TaxonomyItem::query()->firstOrCreate(
            ['taxonomy_id' => $taxonomy->id, 'slug' => Str::slug($name)],
            ['name' => $name],
        );
    }

    /**
     * @param  array<int, int>  $selectedIds
     */
    private function taxonomyItems(string $type, string $search, array $selectedIds = []): Collection
    {
        $search = trim($search);

        $results = TaxonomyItem::query()
            ->forType($type)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->limit(20)
            ->get();

        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));

        if ($search !== '' || $selectedIds === []) {
            return $results;
        }

        $missingSelectedIds = array_values(array_diff($selectedIds, $results->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));

        if ($missingSelectedIds === []) {
            return $results;
        }

        return TaxonomyItem::query()
            ->whereIn('id', $missingSelectedIds)
            ->ordered()
            ->get()
            ->merge($results);
    }

    private function postForCurrentTeam(string $uuid): Post
    {
        $model = config('blog.models.post', Post::class);

        return $model::query()
            ->forTeam($this->currentTeamId())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function currentTeamId(): ?int
    {
        return app(TeamResolver::class)->resolve();
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->successful ? 'success' : 'danger', text: $result->message);
    }
}
