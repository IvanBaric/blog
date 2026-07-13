<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use IvanBaric\Blog\Actions\Taxonomies\CreateTaxonomyItemAction;
use IvanBaric\Blog\Actions\Taxonomies\DeleteTaxonomyItemAction;
use IvanBaric\Blog\Actions\Taxonomies\UpdateTaxonomyItemAction;
use IvanBaric\Blog\Livewire\Forms\TaxonomyItemForm;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class PostTaxonomies extends Component
{
    use WithPagination;

    private ?Taxonomy $resolvedTaxonomy = null;

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
        corexis_authorize('blog.create', $this->currentTeamId());

        try {
            $data = $this->createForm->data();
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::error(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $result = $createTaxonomyItem->execute(
            taxonomy: $this->taxonomy(),
            data: $data,
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->success) {
            return;
        }

        $this->createForm->resetForm();
        unset($this->items, $this->totalItems);
        $this->dispatch('changed');

        Flux::modal('taxonomy-create')->close();
    }

    public function openCreate(): void
    {
        $this->createForm->resetForm();

        Flux::modal('taxonomy-create')->show();
    }

    public function cancelCreate(): void
    {
        $this->createForm->resetForm();
    }

    public function edit(string $uuid): void
    {
        corexis_authorize('blog.update', $this->currentTeamId());

        $item = $this->findItem($uuid);

        $this->reset('editingItemUuid');
        $this->editForm->resetForm();
        $this->editingItemUuid = (string) $item->getAttribute('uuid');
        $this->editForm->fillFromModel($item);

        Flux::modal('taxonomy-edit')->show();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingItemUuid');
        $this->editForm->resetForm();
    }

    public function update(UpdateTaxonomyItemAction $updateTaxonomyItem): void
    {
        corexis_authorize('blog.update', $this->currentTeamId());

        try {
            $data = $this->editForm->data();
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::error(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $result = $updateTaxonomyItem->execute(
            item: $this->findItem((string) $this->editingItemUuid),
            data: $data,
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->success) {
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
        corexis_authorize('blog.delete', $this->currentTeamId());

        $this->deletingItemUuid = (string) $this->findItem($uuid)->getAttribute('uuid');

        Flux::modal('taxonomy-delete')->show();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingItemUuid');
    }

    public function delete(DeleteTaxonomyItemAction $deleteTaxonomyItem): void
    {
        corexis_authorize('blog.delete', $this->currentTeamId());

        $result = $deleteTaxonomyItem->execute(
            item: $this->findItem((string) $this->deletingItemUuid),
            type: $this->type,
        );

        $this->toastFromResult($result);

        if (! $result->success) {
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
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $postMorphClass = $this->postMorphClass();
        $sortField = in_array($this->sortField, ['name', 'posts'], true) ? $this->sortField : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $teamId = $this->currentTeamId();
        $tenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');
        $scopePivotTenant = config('corexis.tenancy.enabled', false) && Schema::hasColumn('taxonomyables', $tenantColumn);

        return $taxonomyItemModel::query()
            ->where('taxonomy_id', $this->taxonomy()->id)
            ->select('taxonomy_items.*')
            ->selectSub(
                DB::table('taxonomyables')
                    ->selectRaw('count(*)')
                    ->whereColumn('taxonomyables.taxonomy_item_id', 'taxonomy_items.id')
                    ->where('taxonomyables.taxonomyable_type', $postMorphClass)
                    ->when($scopePivotTenant, fn ($query) => $query->where('taxonomyables.'.$tenantColumn, $teamId)),
                'posts_count'
            )
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(
                $sortField === 'posts',
                fn ($query) => $query->orderBy('posts_count', $sortDirection)->orderBy('name'),
                fn ($query) => $query->orderBy('name', $sortDirection)
            )
            ->simplePaginate(5);
    }

    #[Computed]
    public function totalItems(): int
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();

        return $taxonomyItemModel::query()
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

        $query = DB::table('taxonomyables')
            ->where('taxonomy_item_id', $item->getKey())
            ->where('taxonomyable_type', $postMorphClass);

        $tenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');

        if (config('corexis.tenancy.enabled', false) && Schema::hasColumn('taxonomyables', $tenantColumn)) {
            $query->where($tenantColumn, $this->currentTeamId());
        }

        return $query->count();
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
        if ($this->resolvedTaxonomy?->exists) {
            return $this->resolvedTaxonomy;
        }

        $identity = ['type' => $this->type, 'slug' => $this->type === 'category' ? 'kategorije' : 'oznake'];

        if (config('corexis.tenancy.enabled', false)) {
            $identity[(string) config('corexis.tenancy.id_column', 'team_id')] = $this->currentTeamId();
        }

        $taxonomyModel = TaxonomyModels::taxonomy();

        return $this->resolvedTaxonomy = $taxonomyModel::query()->firstOrCreate(
            $identity,
            [
                'name' => $this->title(),
                'is_filterable' => true,
                'is_multiple' => $this->type === 'tags',
            ],
        );
    }

    private function findItem(string $uuid): TaxonomyItem
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();

        return $taxonomyItemModel::query()
            ->where('taxonomy_id', $this->taxonomy()->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function postMorphClass(): string
    {
        $postModel = BlogModels::post();

        return (new $postModel)->getMorphClass();
    }

    private function currentTeamId(): ?int
    {
        $tenantId = corexis_tenant_id();

        return is_numeric($tenantId) ? (int) $tenantId : null;
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->success ? 'success' : 'danger', text: $result->message);
    }
}
