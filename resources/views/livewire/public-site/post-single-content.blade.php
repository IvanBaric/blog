@php
    $isHeroPostLayout = $singleLayout === 'hero';
    $isCompactPostLayout = $singleLayout === 'compact';
    $isCoverPostLayout = $singleLayout === 'cover';
    $isSidebarPostLayout = $singleLayout === 'sidebar';
    $coverPhoto = $isCoverPostLayout ? $mediaSlides->first() : null;
    $contentMediaSlides = $isCoverPostLayout && $mediaSlides->isNotEmpty() ? $mediaSlides->slice(1)->values() : $mediaSlides;
    $hasPostIntro = trim((string) ($excerpt ?? '')) !== '';
@endphp

<article id="content" @class([
    'mx-auto w-full scroll-mt-24 px-6 py-12 sm:py-14 lg:py-16',
    'post-single-'.$singleLayout,
    'max-w-5xl' => ! $isHeroPostLayout && ! $isCompactPostLayout && ! $isCoverPostLayout && ! $isSidebarPostLayout,
    'max-w-6xl' => $isHeroPostLayout || $isCoverPostLayout || $isSidebarPostLayout,
    'max-w-3xl' => $isCompactPostLayout,
]) data-public-post-single-content>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ $backUrl }}" class="cx-public-back-link font-normal">
            <flux:icon name="arrow-left" class="size-4" />
            {{ $backLabel }}
        </a>

        @if (corexis_actor_id() !== null)
            @livewire(\IvanBaric\Blog\Livewire\PublicSite\PostSingleActions::class, [
                'post' => $post,
                'section' => $section,
                'currentUrl' => request()->fullUrl(),
            ], key('post-single-actions-'.(string) ($post->uuid ?? $post->id)))
        @endif
    </div>

    @if ($coverPhoto)
        <figure class="relative mt-10 overflow-hidden rounded-xl bg-zinc-900 shadow-sm shadow-zinc-950/5 ring-1 ring-zinc-200/70 dark:ring-zinc-800 dark:shadow-black/20" data-scroll-reveal>
            <img
                src="{{ $coverPhoto['url'] }}"
                alt="{{ $coverPhoto['alt'] }}"
                class="aspect-[16/10] w-full object-cover sm:aspect-[16/8]"
                loading="eager" decoding="async"
            >

            <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-zinc-950/80 via-zinc-950/35 to-transparent px-5 pb-6 pt-24 text-white sm:px-8 sm:pb-8 lg:px-10 lg:pb-10">
                <h1 class="max-w-4xl text-3xl font-normal leading-tight tracking-tight sm:text-4xl lg:text-5xl">
                    {{ $title }}
                </h1>

                @if ($hasPostIntro)
                    <p class="mt-4 max-w-3xl text-lg font-normal leading-8 text-white/85 sm:text-xl">
                        {{ $excerpt }}
                    </p>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-white/80">
                    @if ($dateLabel && $machineDate)
                        <time datetime="{{ $machineDate }}" class="inline-flex items-center gap-1.5">
                            <flux:icon name="calendar-days" class="size-4 text-white/70" />
                            {{ $dateLabel }}
                        </time>
                    @endif

                    @if ($authorName)
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="user" class="size-4 text-white/70" />
                            {{ $authorName }}
                        </span>
                    @endif

                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="clock" class="size-4 text-white/70" />
                        {{ trans_choice(':count minuta|:count minute|:count minuta', $readingMinutes, ['count' => $readingMinutes]) }}
                    </span>

                    @if ($mediaSlides->count() > 1)
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="photo" class="size-4 text-white/70" />
                            {{ $mediaCountLabel }}
                        </span>
                    @endif
                </div>
            </figcaption>
        </figure>
    @else
        <header @class([
            'mt-10',
            'max-w-3xl' => ! $isHeroPostLayout,
            'mx-auto max-w-4xl text-center' => $isHeroPostLayout,
        ]) data-scroll-reveal>
            <h1 @class([
                'font-normal leading-tight tracking-tight text-zinc-950 dark:text-white',
                'text-3xl sm:text-4xl lg:text-5xl' => ! $isHeroPostLayout,
                'text-4xl sm:text-5xl lg:text-6xl' => $isHeroPostLayout,
            ])>
                {{ $title }}
            </h1>

            @if ($hasPostIntro)
                <p @class([
                    'mt-5 text-lg font-normal leading-8 text-zinc-600 dark:text-zinc-300 sm:text-xl',
                    'mx-auto max-w-3xl' => $isHeroPostLayout,
                ])>
                    {{ $excerpt }}
                </p>
            @endif
        </header>

        <div @class([
            'mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-[15px] font-normal text-zinc-500 dark:text-zinc-400',
            'justify-center' => $isHeroPostLayout,
        ]) data-scroll-reveal>
            @if ($dateLabel && $machineDate)
                <time datetime="{{ $machineDate }}" class="inline-flex items-center gap-1.5">
                    <flux:icon name="calendar-days" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                    {{ $dateLabel }}
                </time>
            @endif

            @if ($authorName)
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="user" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                    {{ $authorName }}
                </span>
            @endif

            <span class="inline-flex items-center gap-1.5">
                <flux:icon name="clock" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                {{ trans_choice(':count minuta|:count minute|:count minuta', $readingMinutes, ['count' => $readingMinutes]) }}
            </span>

            @if ($mediaSlides->count() > 1)
                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="photo" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                    {{ $mediaCountLabel }}
                </span>
            @endif
        </div>
    @endif

    @if ($contentMediaSlides->isNotEmpty())
        @if ($contentMediaSlides->count() > 1)
            <section @class([
                'mt-5',
                'mx-auto max-w-5xl' => $isHeroPostLayout,
            ]) aria-label="{{ __('Fotografije objave') }}" data-scroll-reveal>
                <flux:carousel name="{{ $galleryCarouselName }}" arrows:position="inside" fade advance="page">
                    @foreach ($contentMediaSlides as $photo)
                        <flux:carousel.slide class="w-full">
                            <figure class="cx-public-media-frame-surface">
                                <img
                                    src="{{ $photo['url'] }}"
                                    alt="{{ $photo['alt'] }}"
                                    class="aspect-[16/9] w-full object-cover"
                                    loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                >

                                @if (($photo['caption'] ?? '') !== '')
                                    <figcaption class="px-4 py-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                        {{ $photo['caption'] }}
                                    </figcaption>
                                @endif
                            </figure>
                        </flux:carousel.slide>
                    @endforeach
                </flux:carousel>
            </section>
        @else
            @php($photo = $contentMediaSlides->first())

            <figure @class([
                'mt-5 cx-public-media-frame-surface',
                'mx-auto max-w-5xl' => $isHeroPostLayout,
            ]) data-scroll-reveal>
                <img
                    src="{{ $photo['url'] }}"
                    alt="{{ $photo['alt'] }}"
                    class="aspect-[16/9] w-full object-cover"
                    loading="eager" decoding="async"
                >

                @if (($photo['caption'] ?? '') !== '')
                    <figcaption class="px-4 py-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        {{ $photo['caption'] }}
                    </figcaption>
                @endif
            </figure>
        @endif
    @endif

    <div @class([
        'mt-14',
        'max-w-3xl' => ! $isHeroPostLayout && ! $isSidebarPostLayout,
        'mx-auto max-w-3xl' => $isHeroPostLayout,
        'grid gap-12 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start' => $isSidebarPostLayout,
    ]) data-scroll-reveal>
        <div class="min-w-0 max-w-[68ch]">
            <div class="space-y-5 text-lg font-normal leading-8 text-zinc-700 dark:text-zinc-300 [&_a]:cursor-pointer [&_a]:font-normal [&_a]:text-[color:var(--niva-primary-700)] [&_a]:transition [&_a:hover]:text-[color:var(--niva-primary-800)] dark:[&_a]:text-[color:var(--niva-primary-300)] dark:[&_a:hover]:text-[color:var(--niva-primary-200)] [&_b]:font-normal [&_blockquote]:border-l-2 [&_blockquote]:border-[color:var(--niva-primary-200)] [&_blockquote]:pl-4 [&_blockquote]:font-normal [&_blockquote]:italic [&_h2]:text-2xl [&_h2]:font-normal [&_h2]:tracking-tight [&_h2]:text-zinc-950 dark:[&_h2]:text-white [&_h3]:text-xl [&_h3]:font-normal [&_h3]:tracking-tight [&_h3]:text-zinc-950 dark:[&_h3]:text-white [&_li]:ml-5 [&_li]:font-normal [&_li]:leading-8 [&_ol]:list-decimal [&_p]:font-normal [&_p]:leading-8 [&_strong]:font-normal [&_ul]:list-disc">
                {!! $contentHtml !!}
            </div>
        </div>

        @if ($isSidebarPostLayout)
            <aside class="cx-public-surface-plain p-5 text-sm font-normal leading-6 text-zinc-600 dark:text-zinc-300 lg:sticky lg:top-24">
                <p class="font-normal text-zinc-950 dark:text-white">{{ __('Podaci o objavi') }}</p>

                <div class="mt-4 grid gap-3">
                    @if ($dateLabel && $machineDate)
                        <time datetime="{{ $machineDate }}" class="inline-flex items-center gap-2">
                            <flux:icon name="calendar-days" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                            {{ $dateLabel }}
                        </time>
                    @endif

                    @if ($authorName)
                        <span class="inline-flex items-center gap-2">
                            <flux:icon name="user" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                            {{ $authorName }}
                        </span>
                    @endif

                    <span class="inline-flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 text-[color:var(--niva-primary-600)] dark:text-[color:var(--niva-primary-300)]" />
                        {{ trans_choice(':count minuta|:count minute|:count minuta', $readingMinutes, ['count' => $readingMinutes]) }}
                    </span>
                </div>

                @if ($categoryItems->isNotEmpty())
                    <div class="mt-6">
                        <p class="font-normal text-zinc-950 dark:text-white">{{ __('Kategorije') }}</p>
                        <div class="mt-2 grid gap-1.5">
                            @foreach ($categoryItems as $category)
                                <a href="{{ route(config('blog.public_organization.taxonomy_route_name', 'public.organization.posts.taxonomy'), ['organizationSlug' => $organizationSlug, 'pageSlug' => $pageSlug, 'taxonomyKind' => 'kategorija', 'taxonomySlug' => $category['slug']]) }}" class="cx-public-text-link font-normal">
                                    {{ $category['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($tagItems->isNotEmpty())
                    <div class="mt-6">
                        <p class="font-normal text-zinc-950 dark:text-white">{{ __('Oznake') }}</p>
                        <div class="mt-2 grid gap-1.5">
                            @foreach ($tagItems as $tag)
                                <a href="{{ route(config('blog.public_organization.page_route_name', 'public.organization.page'), ['organizationSlug' => $organizationSlug, 'pageSlug' => $pageSlug, 'oznaka' => $tag['slug']]) }}" class="cx-public-text-link font-normal">
                                    #{{ $tag['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        @endif
    </div>

    @if (($previousPost ?? null) || ($nextPost ?? null))
        <nav class="mt-12 grid w-full gap-4 sm:grid-cols-2" aria-label="{{ __('Navigacija objava') }}" data-scroll-reveal>
            @if ($previousPost ?? null)
                <a href="{{ $previousPost['url'] }}" class="group flex cursor-pointer items-start gap-3 cx-public-surface-plain p-5 text-left cx-public-card-hover">
                    <flux:icon name="arrow-left" class="mt-1 size-4 shrink-0 text-zinc-400 transition group-hover:text-[color:var(--niva-primary-700)] dark:text-zinc-500 dark:group-hover:text-[color:var(--niva-primary-300)]" />
                    <span class="min-w-0">
                        <span class="block text-sm font-normal text-zinc-500 dark:text-zinc-400">{{ $previousPostLabel }}</span>
                        <span class="mt-1 block text-base font-normal leading-6 text-zinc-950 transition group-hover:text-[color:var(--niva-primary-800)] dark:text-white dark:group-hover:text-[color:var(--niva-primary-200)]">
                            {{ $previousPost['title'] }}
                        </span>
                    </span>
                </a>
            @else
                <span class="hidden sm:block"></span>
            @endif

            @if ($nextPost ?? null)
                <a href="{{ $nextPost['url'] }}" class="group flex cursor-pointer items-start justify-end gap-3 cx-public-surface-plain p-5 text-left cx-public-card-hover sm:text-right">
                    <span class="min-w-0">
                        <span class="block text-sm font-normal text-zinc-500 dark:text-zinc-400">{{ $nextPostLabel }}</span>
                        <span class="mt-1 block text-base font-normal leading-6 text-zinc-950 transition group-hover:text-[color:var(--niva-primary-800)] dark:text-white dark:group-hover:text-[color:var(--niva-primary-200)]">
                            {{ $nextPost['title'] }}
                        </span>
                    </span>
                    <flux:icon name="arrow-right" class="mt-1 size-4 shrink-0 text-zinc-400 transition group-hover:text-[color:var(--niva-primary-700)] dark:text-zinc-500 dark:group-hover:text-[color:var(--niva-primary-300)]" />
                </a>
            @endif
        </nav>
    @endif

    @if (! $isSidebarPostLayout && ($categoryItems->isNotEmpty() || $tagItems->isNotEmpty()))
        <footer @class([
            'mt-12 max-w-3xl',
            'mx-auto' => $isHeroPostLayout,
        ]) data-scroll-reveal>
            <div @class([
                'grid gap-6 cx-public-surface-plain p-5',
                'sm:grid-cols-2' => $categoryItems->isNotEmpty() && $tagItems->isNotEmpty(),
            ])>
                @if ($categoryItems->isNotEmpty())
                    <div>
                        <p class="inline-flex items-center gap-2 text-base font-normal text-zinc-950 dark:text-white">
                            <flux:icon name="folder" class="size-4 text-[color:var(--niva-primary-700)] dark:text-[color:var(--niva-primary-300)]" />
                            {{ __('Kategorije') }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($categoryItems as $category)
                                <a href="{{ route(config('blog.public_organization.taxonomy_route_name', 'public.organization.posts.taxonomy'), ['organizationSlug' => $organizationSlug, 'pageSlug' => $pageSlug, 'taxonomyKind' => 'kategorija', 'taxonomySlug' => $category['slug']]) }}" class="inline-flex cx-public-text-link text-base font-normal leading-7">
                                    {{ $category['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($tagItems->isNotEmpty())
                    <div>
                        <p class="inline-flex items-center gap-2 text-base font-normal text-zinc-950 dark:text-white">
                            <flux:icon name="tag" class="size-4 text-[color:var(--niva-primary-700)] dark:text-[color:var(--niva-primary-300)]" />
                            {{ __('Oznake') }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($tagItems as $tag)
                                <a href="{{ route(config('blog.public_organization.page_route_name', 'public.organization.page'), ['organizationSlug' => $organizationSlug, 'pageSlug' => $pageSlug, 'oznaka' => $tag['slug']]) }}" class="inline-flex cx-public-text-link text-base font-normal leading-7">
                                    #{{ $tag['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </footer>
    @endif
</article>
