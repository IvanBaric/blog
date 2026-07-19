<x-layouts.public
    :title="$post->localized('title')"
    :seo-model="$post"
    :seo-data="$socialMeta ?? null"
    :organization="$organization"
    :public-pages="$publicPages"
    :template-key="$page ? template_engine()->resolveTemplateKey($page) : null"
    compact-header
>
    @php
        $pagePath = app(\IvanBaric\Pages\Support\PageHierarchy::class)->slugPath($page, $publicPages);
        $backUrl = app(\IvanBaric\Pages\Support\PublicSiteUrl::class)->page($organization, $page, $publicPages);
        $backLabel = filled($backLabel ?? null) ? (string) $backLabel : __('Natrag na objave');
        $previousPostLabel = filled($previousPostLabel ?? null) ? (string) $previousPostLabel : __('Prethodna objava');
        $nextPostLabel = filled($nextPostLabel ?? null) ? (string) $nextPostLabel : __('Sljedeća objava');
        $categoryPayload = collect($categories ?? [])
            ->map(fn (mixed $category): array => [
                'name' => (string) data_get($category, 'name'),
                'slug' => (string) data_get($category, 'slug'),
            ])
            ->values()
            ->all();
        $tagPayload = collect($tags ?? [])
            ->map(fn (mixed $tag): array => [
                'name' => (string) data_get($tag, 'name'),
                'slug' => (string) data_get($tag, 'slug'),
            ])
            ->values()
            ->all();
    @endphp

    <main class="bg-[#fbfaf7] dark:bg-zinc-950">
        @livewire(\IvanBaric\Blog\Livewire\PublicSite\PostSingleContent::class, [
            'post' => $post,
            'section' => $singleLayoutSection ?? null,
            'organizationSlug' => (string) $organization->slug,
            'pageSlug' => $pagePath,
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
            'previousPostLabel' => $previousPostLabel,
            'nextPostLabel' => $nextPostLabel,
            'previousPost' => $previousPost ?? null,
            'nextPost' => $nextPost ?? null,
            'categories' => $categoryPayload,
            'tags' => $tagPayload,
        ], key('post-single-content-'.(string) ($post->uuid ?? $post->id)))
    </main>
</x-layouts.public>
