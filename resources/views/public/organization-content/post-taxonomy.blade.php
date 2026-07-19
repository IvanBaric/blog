<x-layouts.public
    :title="$taxonomyItem->name"
    :seo-data="$socialMeta ?? null"
    :organization="$organization"
    :public-pages="$publicPages"
    :template-key="$page ? template_engine()->resolveTemplateKey($page) : null"
    compact-header
>
    <main class="bg-white px-6 py-14 dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl">
            <a href="{{ app(\IvanBaric\Pages\Support\PublicSiteUrl::class)->page($organization, $page, $publicPages) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[color:var(--niva-primary-700)] transition hover:text-[color:var(--niva-primary-800)] dark:text-[color:var(--niva-primary-300)] dark:hover:text-[color:var(--niva-primary-200)]">
                <span aria-hidden="true">&larr;</span>
                {{ __('Sve objave') }}
            </a>

            <header class="mt-8 max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-[color:var(--niva-primary-700)] dark:text-[color:var(--niva-primary-300)]">
                    {{ $taxonomyKind === 'kategorija' ? __('Kategorija') : __('Oznaka') }}
                </p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $taxonomyItem->name }}</h1>
                <p class="mt-5 text-base leading-7 text-zinc-600 dark:text-zinc-300">
                    {{ trans_choice(':count objava u ovoj skupini.|:count objave u ovoj skupini.|:count objava u ovoj skupini.', $posts->total(), ['count' => $posts->total()]) }}
                </p>
            </header>

            <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($posts as $post)
                    @php($image = $post->featuredImageUrl('large'))
                    <article class="overflow-hidden rounded-lg bg-zinc-50 shadow-sm shadow-zinc-950/5 transition duration-200 hover:-translate-y-0.5 hover:bg-white hover:shadow-md hover:shadow-zinc-950/10 dark:bg-zinc-900 dark:shadow-black/20 dark:hover:bg-zinc-900/80">
                        @if ($image)
                            <a href="{{ app(\IvanBaric\Pages\Support\PublicSiteUrl::class)->content($organization, $page, $post->slug, $publicPages) }}" class="block cursor-pointer" title="{{ $post->localized('title') }}" aria-label="{{ $post->localized('title') }}">
                                <img src="{{ $image }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy" decoding="async">
                            </a>
                        @endif
                        <div class="p-6">
                            @if ($post->published_at)
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $post->published_at->format('d.m.Y.') }}</p>
                            @endif
                            <h2 class="mt-2 text-lg font-semibold tracking-tight text-zinc-950 dark:text-white">
                                <a href="{{ app(\IvanBaric\Pages\Support\PublicSiteUrl::class)->content($organization, $page, $post->slug, $publicPages) }}" class="cursor-pointer transition hover:text-[color:var(--niva-primary-800)] dark:hover:text-[color:var(--niva-primary-200)]">
                                    {{ $post->localized('title') }}
                                </a>
                            </h2>
                            @if ($post->localized('excerpt'))
                                <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ $post->localized('excerpt') }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <x-corexis::public-empty-state
                        :title="__('Objave uskoro')"
                        :description="__('U ovoj skupini trenutačno nema objava za prikaz.')"
                        icon="document-text"
                        class="md:col-span-2 lg:col-span-3"
                    />
                @endforelse
            </div>

            @if ($posts->hasPages())
                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </main>
</x-layouts.public>
