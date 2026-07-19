<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogModels;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

final class PostSourceManager extends Component
{
    use WithoutUrlPagination, WithPagination;

    public string $search = '';

    public string $filter = 'all';

    public string $newPostTitle = '';

    #[Locked]
    public string $activeView = 'posts';

    #[Locked]
    public ?string $editingPostUuid = null;

    public function mount(): void
    {
        corexis_authorize('blog.view', corexis_tenant_id());
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'published', 'draft', 'featured', 'archived'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filter');
        $this->resetPage();
    }

    public function openCreatePost(): void
    {
        corexis_authorize('blog.create', []);
        $this->reset('newPostTitle');
        $this->resetValidation();
        Flux::modal($this->createPostModalName())->show();
    }

    public function cancelCreatePost(): void
    {
        $this->reset('newPostTitle');
        $this->resetValidation();
    }

    public function createPost(CreatePostAction $createPost): void
    {
        corexis_authorize('blog.create', []);

        $validated = $this->validate([
            'newPostTitle' => ['required', 'string', 'max:160'],
        ], [], [
            'newPostTitle' => __('naziv objave'),
        ]);

        $result = $createPost->handle([
            'title' => [$this->currentLocaleCode() => trim((string) $validated['newPostTitle'])],
            'content' => null,
            'context' => config('blog.default_context', 'blog'),
            'status' => config('blog.default_status', 'draft'),
            'published_at' => null,
            'is_featured' => false,
        ]);

        if (! $result->success) {
            foreach ($result->errors as $field => $messages) {
                $this->addError($field === 'title' ? 'newPostTitle' : $field, $messages[0] ?? $result->message);
            }

            Flux::toast(variant: 'danger', text: $result->message);

            return;
        }

        if (! $result->data instanceof Post) {
            Flux::toast(variant: 'danger', text: __('Objava nije mogla biti izrađena.'));

            return;
        }

        $post = $result->data;

        $this->reset('newPostTitle');
        $this->editingPostUuid = (string) $post->getAttribute('uuid');
        unset($this->editingPost, $this->posts, $this->stats);

        Flux::modal($this->createPostModalName())->close();
        Flux::toast(
            heading: __('Objava kreirana'),
            text: __('Nova objava ":title" otvorena je za uređivanje.', ['title' => $post->localized('title')]),
            variant: 'success',
        );

        $this->dispatch('pages-public-content-source-updated', source: 'posts');
    }

    public function editPost(string $postUuid): void
    {
        $post = $this->findPost($postUuid);
        corexis_authorize('blog.update', $post);

        $this->editingPostUuid = (string) $post->getAttribute('uuid');
        unset($this->editingPost);
    }

    public function showList(): void
    {
        $this->editingPostUuid = null;
        $this->resetPage();
        unset($this->editingPost, $this->posts, $this->stats);
    }

    public function showView(string $view): void
    {
        abort_unless(in_array($view, ['posts', 'categories', 'tags'], true), 404);

        $this->activeView = $view;
        $this->showList();
    }

    #[On('blog-source-post-saved')]
    public function postSaved(): void
    {
        $this->showList();
        $this->dispatch('pages-public-content-source-updated', source: 'posts');
    }

    #[On('changed')]
    public function taxonomyChanged(): void
    {
        unset($this->posts);
        $this->dispatch('pages-public-content-source-updated', source: 'posts');
    }

    #[Computed]
    public function editingPost(): ?Post
    {
        return $this->editingPostUuid === null ? null : $this->findPost($this->editingPostUuid);
    }

    #[Computed]
    public function posts(): Paginator
    {
        $model = BlogModels::post();
        $postTable = (new $model)->getTable();
        $search = str($this->search)->trim()->limit(160, '')->toString();
        $filter = in_array($this->filter, ['all', 'published', 'draft', 'featured', 'archived'], true) ? $this->filter : 'all';

        return $model::query()
            ->select([
                $postTable.'.id',
                $postTable.'.team_id',
                $postTable.'.uuid',
                $postTable.'.slug',
                $postTable.'.title',
                $postTable.'.status',
                $postTable.'.published_at',
                $postTable.'.is_featured',
            ])
            ->with([
                'taxonomyItems' => fn ($query) => $query
                    ->whereHas('taxonomy', fn ($query) => $query->whereIn('type', ['category', 'post_category']))
                    ->with('taxonomy'),
                'galleries' => fn ($query) => $query
                    ->whereIn('collection_name', ['images', Post::FEATURED_IMAGE_COLLECTION])
                    ->withCount('media')
                    ->with([
                        'media' => fn ($query) => $query->where('collection_name', Post::FEATURED_IMAGE_COLLECTION),
                    ]),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($filter, ['published', 'draft', 'archived'], true), fn (Builder $query) => $query->where('status', $filter))
            ->when($filter === 'featured', fn (Builder $query) => $query->featured())
            ->ordered()
            ->simplePaginate(max(1, (int) config('corexis.pagination.default_items', 12)));
    }

    #[Computed]
    public function stats(): array
    {
        $model = BlogModels::post();
        $stats = $model::query()
            ->selectRaw('COUNT(*) as aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as aggregate_published")
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as aggregate_draft")
            ->selectRaw("SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as aggregate_archived")
            ->selectRaw(
                "SUM(CASE WHEN status = 'published' AND is_featured = ? THEN 1 ELSE 0 END) as aggregate_featured",
                [true],
            )
            ->first();

        return [
            'total' => (int) $stats?->getAttribute('aggregate_total'),
            'published' => (int) $stats?->getAttribute('aggregate_published'),
            'draft' => (int) $stats?->getAttribute('aggregate_draft'),
            'archived' => (int) $stats?->getAttribute('aggregate_archived'),
            'featured' => (int) $stats?->getAttribute('aggregate_featured'),
        ];
    }

    #[Computed]
    public function filterOptions(): array
    {
        $stats = $this->stats;

        return [
            'all' => ['label' => __('Sve'), 'icon' => 'document-text', 'count' => $stats['total']],
            'published' => ['label' => __('Objavljeno'), 'icon' => 'check-circle', 'count' => $stats['published']],
            'draft' => ['label' => __('Skice'), 'icon' => 'pencil-square', 'count' => $stats['draft']],
            'featured' => ['label' => __('Izdvojeno'), 'icon' => 'sparkles', 'count' => $stats['featured']],
            'archived' => ['label' => __('Arhivirano'), 'icon' => 'archive-box', 'count' => $stats['archived']],
        ];
    }

    #[Computed]
    public function activeFilterLabel(): string
    {
        return match ($this->filter) {
            'published' => __('Objavljene objave'),
            'draft' => __('Skice'),
            'featured' => __('Izdvojene objave'),
            'archived' => __('Arhivirane objave'),
            default => __('Sve objave'),
        };
    }

    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->filter !== 'all';
    }

    public function statusBadge(Post $post): array
    {
        if ($post->status === 'published' && $post->published_at?->isFuture()) {
            return ['label' => __('Zakazano'), 'class' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20'];
        }

        return match ($post->status) {
            'published' => ['label' => __('Objavljeno'), 'class' => 'bg-accent/10 text-accent-content ring-accent/15 dark:bg-accent/15 dark:text-accent-content dark:ring-accent/25'],
            'archived' => ['label' => __('Arhivirano'), 'class' => 'bg-zinc-100 text-zinc-600 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/10'],
            default => ['label' => __('Skica'), 'class' => 'bg-zinc-100 text-zinc-600 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/10'],
        };
    }

    public function categoryLabel(Post $post): string
    {
        $category = $post->taxonomyItems
            ->first(fn ($item): bool => in_array((string) data_get($item, 'taxonomy.type'), ['category', 'post_category'], true));

        return data_get($category, 'name') ?: __('Bez kategorije');
    }

    public function publishedAt(Post $post): string
    {
        return $post->published_at?->format('d.m.Y. H:i') ?? __('Nije objavljeno');
    }

    public function render(): View
    {
        return view('blog::livewire.admin.post-source-manager');
    }

    public function createPostModalName(): string
    {
        return 'source-post-create';
    }

    private function findPost(string $postUuid): Post
    {
        $model = BlogModels::post();
        $post = $model::query()->where('uuid', $postUuid)->first();

        abort_unless($post instanceof Post, 404);

        return $post;
    }

    private function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }
}
