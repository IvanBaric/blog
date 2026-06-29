<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use IvanBaric\Blog\Actions\Taxonomies\CreateTaxonomyItemAction;
use IvanBaric\Blog\Actions\Taxonomies\DeleteTaxonomyItemAction;
use IvanBaric\Blog\Actions\Taxonomies\UpdateTaxonomyItemAction;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Livewire\Forms\TaxonomyItemForm;
use IvanBaric\Blog\Support\TeamResolver;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class PostTaxonomies extends Component
{
    use WithPagination;

    #[Locked]
    public string $type = 'category';

    public TaxonomyItemForm $createForm;

    public TaxonomyItemForm $editForm;

    public string $search = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    #[Locked]
    public ?string $editingItemUuid = null;

    #[Locked]
    public ?string $deletingItemUuid = null;

    public function mount(string $type): void
    {
        abort_unless(in_array($type, ['category', 'tags'], true), 404);

        corexis_authorize('blog.view', $this->currentTeamId());

        $this->type = $type;
    }

    public function save(CreateTaxonomyItemAction $createTaxonomyItem): void
    {
        try {
            $data = $this->createForm->data();
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::failure(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $result = $createTaxonomyItem->execute(
            taxonomy: $this->taxonomy(),
            data: $data,
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->successful) {
            return;
        }

        $this->createForm->resetForm();
        unset($this->items, $this->totalItems);
        $this->dispatch('changed');
    }

    public function edit(string $uuid): void
    {
        $item = $this->findItem($uuid);

        $this->editingItemUuid = (string) $item->uuid;
        $this->editForm->fillFromModel($item);
    }

    public function update(UpdateTaxonomyItemAction $updateTaxonomyItem): void
    {
        try {
            $data = $this->editForm->data();
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::failure(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $result = $updateTaxonomyItem->execute(
            item: $this->findItem((string) $this->editingItemUuid),
            data: $data,
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->successful) {
            return;
        }

        $this->reset('editingItemUuid');
        $this->editForm->resetForm();
        unset($this->items);
        $this->dispatch('changed');

        Flux::modal('taxonomy-edit')->close();
    }

    public function confirmDelete(string $uuid): void
    {
        $this->deletingItemUuid = (string) $this->findItem($uuid)->uuid;
    }

    public function delete(DeleteTaxonomyItemAction $deleteTaxonomyItem): void
    {
        $result = $deleteTaxonomyItem->execute(
            item: $this->findItem((string) $this->deletingItemUuid),
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->successful) {
            return;
        }

        $this->reset('deletingItemUuid');
        unset($this->items, $this->totalItems);
        $this->dispatch('changed');

        Flux::modal('taxonomy-delete')->close();
    }

    /** @return Paginator<int, TaxonomyItem> */
    #[Computed]
    public function items(): Paginator
    {
        $postMorphClass = $this->postMorphClass();
        $sortField = in_array($this->sortField, ['name', 'posts'], true) ? $this->sortField : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return TaxonomyItem::query()
            ->where('taxonomy_id', $this->taxonomy()->id)
            ->when($postMorphClass !== null, function ($query) use ($postMorphClass): void {
                $query->select('taxonomy_items.*')
                    ->selectSub(
                        DB::table('taxonomyables')
                            ->selectRaw('count(*)')
                            ->whereColumn('taxonomyables.taxonomy_item_id', 'taxonomy_items.id')
                            ->where('taxonomyables.taxonomyable_type', $postMorphClass),
                        'posts_count'
                    );
            })
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(
                $sortField === 'posts' && $postMorphClass !== null,
                fn ($query) => $query->orderBy('posts_count', $sortDirection)->orderBy('name'),
                fn ($query) => $query->orderBy('name', $sortDirection)
            )
            ->simplePaginate(5);
    }

    #[Computed]
    public function totalItems(): int
    {
        return TaxonomyItem::query()
            ->where('taxonomy_id', $this->taxonomy()->id)
            ->count();
    }

    public function resetSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'posts'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortField !== $field) {
            return 'chevron-up-down';
        }

        return $this->sortDirection === 'asc' ? 'chevron-up' : 'chevron-down';
    }

    public function postCount(TaxonomyItem $item): int
    {
        if (isset($item->posts_count)) {
            return (int) $item->posts_count;
        }

        $postMorphClass = $this->postMorphClass();

        if ($postMorphClass === null) {
            return 0;
        }

        return DB::table('taxonomyables')
            ->where('taxonomy_item_id', $item->getKey())
            ->where('taxonomyable_type', $postMorphClass)
            ->count();
    }

    public function render(): View
    {
        return view('blog::livewire.admin.post-taxonomies')
            ->layout(config('blog.admin_ui.layout', 'layouts.app'), [
                'title' => $this->title(),
            ]);
    }

    public function title(): string
    {
        return $this->type === 'category' ? __('Kategorije') : __('Oznake');
    }

    public function descriptionText(): string
    {
        return $this->type === 'category'
            ? __('Kategorije služe za glavnu organizaciju objava.')
            : __('Oznake pomažu povezati objave po temama i ključnim pojmovima.');
    }

    private function taxonomy(): Taxonomy
    {
        return Taxonomy::query()->firstOrCreate(
            ['type' => $this->type, 'slug' => $this->type === 'category' ? 'kategorije' : 'oznake'],
            [
                'name' => $this->title(),
                'is_filterable' => true,
                'is_multiple' => $this->type === 'tags',
            ],
        );
    }

    private function findItem(string $uuid): TaxonomyItem
    {
        return TaxonomyItem::query()
            ->where('taxonomy_id', $this->taxonomy()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function postMorphClass(): ?string
    {
        $postModel = config('blog.models.post');
        $post = is_string($postModel) && is_subclass_of($postModel, Model::class)
            ? new $postModel
            : null;

        return $post instanceof Model ? $post->getMorphClass() : null;
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
