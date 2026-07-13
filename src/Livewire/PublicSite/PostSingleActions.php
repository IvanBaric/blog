<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\PublicSite;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class PostSingleActions extends Component
{
    /** @var array<string, string> */
    private const LAYOUTS = [
        'classic' => 'Klasični prikaz',
        'hero' => 'Hero prikaz',
        'compact' => 'Sažeti editorial prikaz',
        'cover' => 'Naslovna fotografija',
        'sidebar' => 'Tekst s bočnim podacima',
    ];

    #[Locked]
    public string $postUuid = '';

    #[Locked]
    public ?string $sectionUuid = null;

    #[Locked]
    public string $currentUrl = '';

    public function mount(Post $post, ?Model $section = null, ?string $currentUrl = null): void
    {
        $this->postUuid = (string) $post->getAttribute('uuid');
        $this->sectionUuid = $section ? (string) $section->getAttribute('uuid') : null;
        $this->currentUrl = $currentUrl ?: url()->current();
    }

    public function cycleSinglePostLayout(): void
    {
        $post = $this->post();
        $section = $this->section();

        abort_unless($post && $section && $this->canManagePost($post) && $this->canManageSection($section), 403);

        $settings = (array) $section->getAttribute('settings');
        $currentLayout = (string) data_get($settings, 'single_layout', 'classic');
        $currentLayout = array_key_exists($currentLayout, self::LAYOUTS) ? $currentLayout : 'classic';
        $layoutKeys = array_keys(self::LAYOUTS);
        $currentIndex = array_search($currentLayout, $layoutKeys, true);
        $nextLayout = $layoutKeys[((int) $currentIndex + 1) % count($layoutKeys)];

        data_set($settings, 'single_layout', $nextLayout);

        $section->forceFill(['settings' => $settings])->save();

        $this->dispatch('single-post-layout-cycled', sectionUuid: (string) $section->getAttribute('uuid'));
    }

    public function render(): View
    {
        $editUrl = $this->editUrl();
        $canCycleSinglePostLayout = $this->canCycleSinglePostLayout();

        return view('blog::livewire.public-site.post-single-actions', [
            'editUrl' => $editUrl,
            'canCycleSinglePostLayout' => $canCycleSinglePostLayout,
            'nextSinglePostLayoutLabel' => $canCycleSinglePostLayout ? $this->nextSinglePostLayoutLabel() : null,
        ]);
    }

    private function editUrl(): ?string
    {
        $post = $this->post();

        $routeName = config('blog.routes.admin_name_prefix', 'admin.blog.').'edit';

        if (! $post || ! $this->canManagePost($post) || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName, ['post' => $post->getAttribute('uuid')]);
    }

    private function canCycleSinglePostLayout(): bool
    {
        $section = $this->section();

        return $section !== null && $this->canManageSection($section);
    }

    private function nextSinglePostLayoutLabel(): string
    {
        $section = $this->section();
        $settings = (array) $section?->getAttribute('settings');
        $currentLayout = (string) data_get($settings, 'single_layout', 'classic');
        $currentLayout = array_key_exists($currentLayout, self::LAYOUTS) ? $currentLayout : 'classic';
        $layoutKeys = array_keys(self::LAYOUTS);
        $currentIndex = array_search($currentLayout, $layoutKeys, true);
        $nextLayout = $layoutKeys[((int) $currentIndex + 1) % count($layoutKeys)];

        return __(self::LAYOUTS[$nextLayout]);
    }

    private function canManagePost(Post $post): bool
    {
        return $this->currentUserTeamMatches(data_get($post, 'team_id'))
            && corexis_authorization_result('blog.update', $post) === null;
    }

    private function canManageSection(Model $section): bool
    {
        return in_array((string) $section->getAttribute('type'), ['latest_news', 'featured_news'], true)
            && $this->currentUserTeamMatches(data_get($section, 'team_id'))
            && corexis_authorization_result('pages.update', $section) === null;
    }

    private function currentUserTeamMatches(mixed $teamId): bool
    {
        $currentTenantId = corexis_tenant_id();

        return corexis_actor_id() !== null
            && is_numeric($teamId)
            && is_numeric($currentTenantId)
            && (int) $teamId === (int) $currentTenantId;
    }

    private function post(): ?Post
    {
        if ($this->postUuid === '') {
            return null;
        }

        $model = BlogModels::post();

        return $model::query()
            ->published()
            ->where('uuid', $this->postUuid)
            ->first();
    }

    private function section(): ?Model
    {
        if (! $this->sectionUuid) {
            return null;
        }

        $sectionModel = BlogConfigResolver::pagesSectionModel();

        if ($sectionModel === null) {
            return null;
        }

        return $sectionModel::query()->where('uuid', $this->sectionUuid)->first();
    }
}
