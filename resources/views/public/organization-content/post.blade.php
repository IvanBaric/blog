<x-layouts.public
    :title="$post->localized('title')"
    :seo-model="$post"
    :organization="$organization"
    :public-pages="$publicPages"
    :template-key="$page ? template_engine()->resolveTemplateKey($page) : null"
    compact-header
>
    @php
        $backUrl = route(config('blog.public_organization.page_route_name', 'public.organization.page'), ['organizationSlug' => $organization->slug, 'pageSlug' => $page->slug]);
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
            'pageSlug' => (string) $page->slug,
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
