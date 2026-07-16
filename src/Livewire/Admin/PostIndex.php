<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use IvanBaric\Blog\Actions\ArchivePostAction;
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Actions\DeletePostAction;
use IvanBaric\Blog\Actions\PublishPostAction;
use IvanBaric\Blog\Actions\ToggleFeaturedPostAction;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Corexis\Data\ActionResult;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class PostIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all';

    public string $newPostTitle = '';

    #[Locked]
    public ?string $archivingPostUuid = null;

    #[Locked]
    public string $archivingPostTitle = '';

    #[Locked]
    public ?string $publishingPostUuid = null;

    #[Locked]
    public string $publishingPostTitle = '';

    #[Locked]
    public bool $publishingPostWillPublish = false;

    #[Locked]
    public bool $publishingPostIsArchived = false;

    #[Locked]
    public ?string $featuringPostUuid = null;

    #[Locked]
    public string $featuringPostTitle = '';

    #[Locked]
    public bool $featuringPostWillFeature = false;

    #[Locked]
    public ?string $deletingPostUuid = null;

    #[Locked]
    public string $deletingPostTitle = '';

    #[Locked]
    public string $deletingPostStatus = '';

    #[Locked]
    public ?string $deletingPostGalleryTitle = null;

    #[Locked]
    public int $deletingPostGalleryPhotoCount = 0;

    public function mount(): void
    {
        corexis_authorize('blog.view', $this->currentTeamId());
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
        $this->reset('newPostTitle');
        Flux::modal('post-create-form')->show();
    }

    public function cancelCreatePost(): void
    {
        $this->reset('newPostTitle');
    }

    public function createPost(CreatePostAction $createPost): void
    {
        $validated = $this->validate([
            'newPostTitle' => ['required', 'string', 'max:160'],
        ], [], [
            'newPostTitle' => __('naziv objave'),
        ]);

        $result = $createPost->handle([
            'team_id' => $this->currentTeamId(),
            'title' => [$this->currentLocaleCode() => trim((string) $validated['newPostTitle'])],
            'content' => null,
            'context' => config('blog.default_context', 'blog'),
            'status' => config('blog.default_status', 'draft'),
            'published_at' => null,
            'is_featured' => false,
        ]);

        if (! $result->success) {
            if ($result->errors !== []) {
                foreach ($result->errors as $field => $messages) {
                    $this->addError($field, $messages[0] ?? $result->message);
                }
            }

            $this->toastFromResult($result);

            return;
        }

        if (! $result->data instanceof Post) {
            Flux::toast(variant: 'danger', text: __('Objava nije mogla biti izrađena.'));

            return;
        }

        $post = $result->data;

        $this->reset('newPostTitle');
        unset($this->posts, $this->stats);

        Flux::modal('post-create-form')->close();
        $this->toastFromResult($result);
        $this->redirectRoute(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $post->uuid], navigate: true);
    }

    public function publish(string $uuid, PublishPostAction $publishPost): void
    {
        $post = $this->findPost($uuid);
        $result = $publishPost->handle($post, ! $post->isPublished());

        $this->toastFromResult($result);
    }

    public function confirmPublish(string $uuid): void
    {
        $post = $this->findPost($uuid);

        $this->publishingPostUuid = (string) $post->uuid;
        $this->publishingPostTitle = $post->localized('title') ?: __('Neimenovana objava');
        $this->publishingPostIsArchived = $post->status === 'archived';
        $this->publishingPostWillPublish = ! $this->publishingPostIsArchived && ! $post->isPublished();

        Flux::modal('post-publish-confirm')->show();
    }

    public function cancelPublish(): void
    {
        $this->reset('publishingPostUuid', 'publishingPostTitle', 'publishingPostWillPublish', 'publishingPostIsArchived');
    }

    public function confirmPublishChange(PublishPostAction $publishPost): void
    {
        if (! $this->publishingPostUuid) {
            return;
        }

        $post = $this->findPost($this->publishingPostUuid);

        if ($this->publishingPostIsArchived && $post->status !== 'archived') {
            $this->reset('publishingPostUuid', 'publishingPostTitle', 'publishingPostWillPublish', 'publishingPostIsArchived');
            Flux::modal('post-publish-confirm')->close();
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite popis i pokušajte ponovno.'));

            return;
        }

        $result = $publishPost->handle(
            $post,
            $this->publishingPostWillPublish,
        );

        $this->reset('publishingPostUuid', 'publishingPostTitle', 'publishingPostWillPublish', 'publishingPostIsArchived');
        unset($this->posts, $this->stats);

        Flux::modal('post-publish-confirm')->close();
        $this->toastFromResult($result);
    }

    public function toggleFeatured(string $uuid, ToggleFeaturedPostAction $toggleFeaturedPost): void
    {
        $result = $toggleFeaturedPost->handle($this->findPost($uuid));

        $this->toastFromResult($result);
    }

    public function confirmFeatured(string $uuid): void
    {
        $post = $this->findPost($uuid);

        if ($post->status !== 'published') {
            return;
        }

        $this->featuringPostUuid = (string) $post->uuid;
        $this->featuringPostTitle = $post->localized('title') ?: __('Neimenovana objava');
        $this->featuringPostWillFeature = ! (bool) $post->is_featured;

        Flux::modal('post-featured-confirm')->show();
    }

    public function cancelFeatured(): void
    {
        $this->reset('featuringPostUuid', 'featuringPostTitle', 'featuringPostWillFeature');
    }

    public function confirmFeaturedChange(ToggleFeaturedPostAction $toggleFeaturedPost): void
    {
        if (! $this->featuringPostUuid) {
            return;
        }

        $result = $toggleFeaturedPost->handle($this->findPost($this->featuringPostUuid));

        $this->reset('featuringPostUuid', 'featuringPostTitle', 'featuringPostWillFeature');
        unset($this->posts, $this->stats);

        Flux::modal('post-featured-confirm')->close();
        $this->toastFromResult($result);
    }

    public function confirmArchive(string $uuid): void
    {
        $post = $this->findPost($uuid);

        if ($post->status === 'archived') {
            return;
        }

        $this->archivingPostUuid = (string) $post->uuid;
        $this->archivingPostTitle = $post->localized('title') ?: __('Neimenovana objava');

        Flux::modal('post-archive-confirm')->show();
    }

    public function cancelArchive(): void
    {
        $this->reset('archivingPostUuid', 'archivingPostTitle');
    }

    public function archive(ArchivePostAction $archivePost): void
    {
        if (! $this->archivingPostUuid) {
            return;
        }

        $result = $archivePost->handle($this->findPost($this->archivingPostUuid));

        $this->reset('archivingPostUuid', 'archivingPostTitle');
        unset($this->posts, $this->stats);

        Flux::modal('post-archive-confirm')->close();
        $this->toastFromResult($result);
    }

    public function confirmDelete(string $uuid): void
    {
        $post = $this->findPost($uuid);

        if (! in_array($post->status, ['draft', 'archived'], true)) {
            Flux::toast(variant: 'danger', text: __('Objavljenu objavu prvo arhivirajte prije brisanja.'));

            return;
        }

        $this->deletingPostUuid = (string) $post->uuid;
        $this->deletingPostTitle = $post->localized('title') ?: __('Neimenovana objava');
        $this->deletingPostStatus = $post->status;
        $gallery = $post->gallery('images');
        $this->deletingPostGalleryTitle = $gallery?->displayTitle();
        $this->deletingPostGalleryPhotoCount = $gallery?->media()->count() ?? 0;

        Flux::modal('post-delete-confirm')->show();
    }

    public function cancelDelete(): void
    {
        $this->reset(
            'deletingPostUuid',
            'deletingPostTitle',
            'deletingPostStatus',
            'deletingPostGalleryTitle',
            'deletingPostGalleryPhotoCount',
        );
    }

    public function delete(DeletePostAction $deletePost): void
    {
        if (! $this->deletingPostUuid) {
            return;
        }

        $post = $this->findPost($this->deletingPostUuid);

        if ($post->status !== $this->deletingPostStatus) {
            $this->cancelDelete();
            Flux::modal('post-delete-confirm')->close();
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite popis i pokušajte ponovno.'));

            return;
        }

        $result = $deletePost->handle($post);

        $this->cancelDelete();
        unset($this->posts, $this->stats);

        Flux::modal('post-delete-confirm')->close();

        $this->toastFromResult($result);
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
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%");
                });
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
    public function statCards(): array
    {
        $stats = $this->stats;

        return [
            ['label' => __('Ukupno objava'), 'value' => $stats['total'], 'icon' => 'document-text', 'accent' => 'bg-zinc-900 dark:bg-white'],
            ['label' => __('Objavljeno'), 'value' => $stats['published'], 'icon' => 'check-circle', 'accent' => 'bg-emerald-500'],
            ['label' => __('Skice'), 'value' => $stats['draft'], 'icon' => 'pencil-square', 'accent' => 'bg-sky-500'],
            ['label' => __('Izdvojeno'), 'value' => $stats['featured'], 'icon' => 'sparkles', 'accent' => 'bg-amber-400'],
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
        if (! $post->published_at) {
            return __('Nije objavljeno');
        }

        return $post->published_at->format('d.m.Y. H:i');
    }

    public function render()
    {
        return view('blog::livewire.admin.post-index')
            ->layout(config('blog.admin_ui.layout', 'layouts.app'), [
                'title' => __('Objave'),
            ]);
    }

    private function findPost(string $uuid): Post
    {
        $model = BlogModels::post();

        return $model::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function currentTeamId(): ?int
    {
        $tenantId = corexis_tenant_id();

        return is_numeric($tenantId) ? (int) $tenantId : null;
    }

    private function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->success ? 'success' : 'danger', text: $result->message);
    }
}
