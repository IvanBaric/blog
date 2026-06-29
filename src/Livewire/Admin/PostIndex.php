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
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\TeamResolver;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

final class PostIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $taxonomy = '';

    public string $filter = 'all';

    public string $newPostTitle = '';

    public ?string $archivingPostUuid = null;

    public string $archivingPostTitle = '';

    public ?string $publishingPostUuid = null;

    public string $publishingPostTitle = '';

    public bool $publishingPostWillPublish = false;

    public ?string $featuringPostUuid = null;

    public string $featuringPostTitle = '';

    public bool $featuringPostWillFeature = false;

    public function mount(): void
    {
        corexis_authorize('blog.view', $this->currentTeamId());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'published', 'draft', 'featured', 'archived'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->status = in_array($filter, ['published', 'draft', 'archived'], true) ? $filter : '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'taxonomy', 'filter');
        $this->resetPage();
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

        if (! $result->successful) {
            if (is_object($result->data) && method_exists($result->data, 'messages')) {
                foreach ($result->data->messages() as $field => $messages) {
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

        if ($post->status === 'archived') {
            return;
        }

        $this->publishingPostUuid = (string) $post->uuid;
        $this->publishingPostTitle = $post->localized('title') ?: __('Neimenovana objava');
        $this->publishingPostWillPublish = ! $post->isPublished();

        Flux::modal('post-publish-confirm')->show();
    }

    public function confirmPublishChange(PublishPostAction $publishPost): void
    {
        if (! $this->publishingPostUuid) {
            return;
        }

        $result = $publishPost->handle(
            $this->findPost($this->publishingPostUuid),
            $this->publishingPostWillPublish,
        );

        $this->reset('publishingPostUuid', 'publishingPostTitle', 'publishingPostWillPublish');
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

        if ($post->status === 'archived') {
            return;
        }

        $this->featuringPostUuid = (string) $post->uuid;
        $this->featuringPostTitle = $post->localized('title') ?: __('Neimenovana objava');
        $this->featuringPostWillFeature = ! (bool) $post->is_featured;

        Flux::modal('post-featured-confirm')->show();
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

    public function delete(string $uuid, DeletePostAction $deletePost): void
    {
        $result = $deletePost->handle($this->findPost($uuid));

        $this->toastFromResult($result);
    }

    #[Computed]
    public function posts(): Paginator
    {
        $model = config('blog.models.post', Post::class);

        return $model::query()
            ->forTeam($this->currentTeamId())
            ->with('taxonomyItems.taxonomy')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('title', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->filter === 'featured', fn (Builder $query) => $query->where('is_featured', true))
            ->ordered()
            ->simplePaginate(6);
    }

    #[Computed]
    public function stats(): array
    {
        $model = config('blog.models.post', Post::class);

        return [
            'total' => $model::query()->forTeam($this->currentTeamId())->count(),
            'published' => $model::query()->forTeam($this->currentTeamId())->where('status', 'published')->count(),
            'draft' => $model::query()->forTeam($this->currentTeamId())->where('status', 'draft')->count(),
            'archived' => $model::query()->forTeam($this->currentTeamId())->where('status', 'archived')->count(),
            'featured' => $model::query()->forTeam($this->currentTeamId())->where('is_featured', true)->count(),
        ];
    }

    #[Computed]
    public function statCards(): array
    {
        return [
            ['label' => __('Ukupno objava'), 'value' => $this->stats['total'], 'icon' => 'document-text', 'accent' => 'bg-zinc-900 dark:bg-white'],
            ['label' => __('Objavljeno'), 'value' => $this->stats['published'], 'icon' => 'check-circle', 'accent' => 'bg-emerald-500'],
            ['label' => __('Skice'), 'value' => $this->stats['draft'], 'icon' => 'pencil-square', 'accent' => 'bg-sky-500'],
            ['label' => __('Izdvojeno'), 'value' => $this->stats['featured'], 'icon' => 'sparkles', 'accent' => 'bg-amber-400'],
        ];
    }

    #[Computed]
    public function filterOptions(): array
    {
        $stats = $this->stats();

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
        return $this->search !== '' || $this->filter !== 'all' || $this->status !== '' || $this->taxonomy !== '';
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
            ->first(fn ($item): bool => (string) data_get($item, 'taxonomy.type') === 'category');

        return $category?->name ?: __('Bez kategorije');
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

    private function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->successful ? 'success' : 'danger', text: $result->message);
    }
}
