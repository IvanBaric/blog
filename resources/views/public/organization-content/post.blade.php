<x-layouts.public
    :title="$post->localized('title')"
    :seo-model="$post"
    :organization="$organization"
    :public-pages="$publicPages"
    :template-key="$page ? template_engine()->resolveTemplateKey($page) : null"
    compact-header
>
    @php
        $backUrl = route('public.organization.page', ['organizationSlug' => $organization->slug, 'pageSlug' => $page->slug]);
        $backLabel = filled($backLabel ?? null) ? (string) $backLabel : __('Natrag na objave');
        $previousPostLabel = filled($previousPostLabel ?? null) ? (string) $previousPostLabel : __('Prethodna objava');
        $nextPostLabel = filled($nextPostLabel ?? null) ? (string) $nextPostLabel : __('Sljedeća objava');
        $title = $post->localized('title');
        $content = str($post->localized('content'))->trim()->toString();
        $dateLabel = $post->published_at?->copy()->locale(app()->getLocale())->translatedFormat('j. F Y.');
        $machineDate = $post->published_at?->toDateString();
        $authorName = data_get($post, 'author.name')
            ?: data_get($post->meta, 'author_name')
            ?: data_get($post->meta, 'author');

        $wordMatches = [];
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($content), $wordMatches);
        $readingMinutes = max(1, (int) ceil(count($wordMatches[0] ?? []) / 180));

        if (str_contains($content, '<')) {
            $contentHtml = strip_tags($content, '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><blockquote>');
            $contentHtml = preg_replace('/<p>\s*(?:&nbsp;|\s|<br\s*\/?>)*<\/p>/i', '', $contentHtml) ?? $contentHtml;
        } else {
            $paragraphs = preg_split('/\R{2,}/', $content) ?: [];
            $contentHtml = collect($paragraphs)
                ->map(fn (string $paragraph): string => trim($paragraph))
                ->filter()
                ->map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph)).'</p>')
                ->implode('');
        }

        $image = null;

        if ($post->featured_image) {
            $image = str_starts_with($post->featured_image, 'http://') || str_starts_with($post->featured_image, 'https://')
                ? $post->featured_image
                : \Illuminate\Support\Facades\Storage::disk('public')->url($post->featured_image);
        }

        $gallery = method_exists($post, 'gallery') ? $post->gallery('images') : null;
        $galleryMedia = $gallery ? $gallery->getMedia('images')->values() : collect();
        $galleryCarouselName = 'post-gallery-carousel-'.(string) ($post->uuid ?? $post->id);

        $mediaUrl = static function (mixed $media, array $preferred): ?string {
            if (! is_object($media) || ! method_exists($media, 'getUrl')) {
                return null;
            }

            foreach (array_filter($preferred) as $conversion) {
                try {
                    if (method_exists($media, 'getAvailableUrl')) {
                        $url = $media->getAvailableUrl([(string) $conversion]);

                        if (filled($url)) {
                            return $url;
                        }
                    }

                    if (method_exists($media, 'hasGeneratedConversion') && ! $media->hasGeneratedConversion((string) $conversion)) {
                        continue;
                    }

                    $url = $media->getUrl((string) $conversion);

                    if (filled($url)) {
                        return $url;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            try {
                return $media->getUrl();
            } catch (\Throwable) {
                return null;
            }
        };

        $galleryPhotos = $galleryMedia
            ->map(function (mixed $media, int $index) use ($mediaUrl, $title, $gallery): ?array {
                $imageUrl = $mediaUrl($media, ['large']);

                if (! $imageUrl) {
                    return null;
                }

                $fallbackAlt = ($gallery?->displayTitle() ?: $title ?: __('Galerija objave')).' '.__('fotografija').' '.($index + 1);
                $caption = trim((string) ($media->getCustomProperty('caption', '') ?: $media->getCustomProperty('title', '') ?: ''));
                $alt = method_exists($media, 'altText') ? $media->altText($fallbackAlt) : (string) ($media->name ?? $fallbackAlt);

                return [
                    'url' => $imageUrl,
                    'alt' => $alt,
                    'caption' => $caption,
                ];
            })
            ->filter()
            ->values();

        $mediaSlides = collect();

        if ($image) {
            $mediaSlides->push([
                'url' => $image,
                'alt' => $title,
                'caption' => '',
            ]);
        }

        $mediaSlides = $mediaSlides
            ->merge($galleryPhotos)
            ->filter(fn (array $photo): bool => filled($photo['url'] ?? null))
            ->values();

        $mediaCountLabel = trans_choice(':count fotografija|:count fotografije|:count fotografija', $mediaSlides->count(), ['count' => $mediaSlides->count()]);
    @endphp

    <main class="bg-[#fbfaf7] dark:bg-zinc-950">
        <article id="content" class="mx-auto w-full max-w-5xl scroll-mt-24 px-6 py-10 sm:py-12 lg:py-14">
            <a href="{{ $backUrl }}" class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-600 transition hover:text-[color:var(--niva-primary-800)] dark:text-zinc-300 dark:hover:text-[color:var(--niva-primary-200)]">
                <flux:icon name="arrow-left" class="size-4" />
                {{ $backLabel }}
            </a>

            <header class="mt-8 max-w-3xl" data-scroll-reveal>
                <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white sm:text-3xl lg:text-4xl">
                    {{ $title }}
                </h1>

            </header>

            <div class="mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400" data-scroll-reveal>
                @if ($dateLabel && $machineDate)
                    <time datetime="{{ $machineDate }}" class="inline-flex items-center gap-1.5">
                        <flux:icon name="calendar-days" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        {{ $dateLabel }}
                    </time>
                @endif

                @if ($authorName)
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="user" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        {{ $authorName }}
                    </span>
                @endif

                <span class="inline-flex items-center gap-1.5">
                    <flux:icon name="clock" class="size-4 text-zinc-400 dark:text-zinc-500" />
                    {{ trans_choice(':count minuta|:count minute|:count minuta', $readingMinutes, ['count' => $readingMinutes]) }}
                </span>

                @if ($mediaSlides->count() > 1)
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="photo" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        {{ $mediaCountLabel }}
                    </span>
                @endif
            </div>

            @if ($mediaSlides->isNotEmpty())
                @if ($mediaSlides->count() > 1)
                    <section class="mt-5" aria-label="{{ __('Fotografije objave') }}" data-scroll-reveal>
                        <flux:carousel name="{{ $galleryCarouselName }}" arrows:position="inside" fade advance="page">
                            @foreach ($mediaSlides as $photo)
                                <flux:carousel.slide class="w-full">
                                    <figure class="overflow-hidden rounded-xl bg-white shadow-sm shadow-zinc-950/5 ring-1 ring-zinc-200/70 dark:bg-zinc-950 dark:ring-zinc-800 dark:shadow-black/20">
                                        <img
                                            src="{{ $photo['url'] }}"
                                            alt="{{ $photo['alt'] }}"
                                            class="aspect-[16/9] w-full object-cover"
                                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
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
                    @php($photo = $mediaSlides->first())

                    <figure class="mt-5 overflow-hidden rounded-xl bg-white shadow-sm shadow-zinc-950/5 ring-1 ring-zinc-200/70 dark:bg-zinc-950 dark:ring-zinc-800 dark:shadow-black/20" data-scroll-reveal>
                        <img
                            src="{{ $photo['url'] }}"
                            alt="{{ $photo['alt'] }}"
                            class="aspect-[16/9] w-full object-cover"
                            loading="eager"
                        >

                        @if (($photo['caption'] ?? '') !== '')
                            <figcaption class="px-4 py-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                {{ $photo['caption'] }}
                            </figcaption>
                        @endif
                    </figure>
                @endif
            @endif

            <div class="mt-12 max-w-3xl" data-scroll-reveal>
                @if ($content !== '')
                    <div class="prose prose-zinc max-w-none text-lg leading-8 prose-headings:font-semibold prose-headings:tracking-tight prose-h2:mt-12 prose-h2:text-2xl prose-h3:mt-10 prose-h3:text-xl prose-p:text-lg prose-p:leading-8 prose-a:cursor-pointer prose-a:text-[color:var(--niva-primary-700)] prose-a:no-underline prose-blockquote:border-[color:var(--niva-primary-200)] prose-blockquote:bg-white/70 prose-blockquote:px-5 prose-blockquote:py-4 prose-blockquote:not-italic prose-li:text-lg prose-li:leading-8 dark:prose-invert dark:prose-a:text-[color:var(--niva-primary-300)] dark:prose-blockquote:bg-zinc-900/70 dark:prose-blockquote:border-[color:var(--niva-primary-900)] [&_a]:transition [&_a:hover]:text-[color:var(--niva-primary-800)] dark:[&_a:hover]:text-[color:var(--niva-primary-200)] [&_li]:text-lg [&_li]:leading-8 [&_p]:mb-6 [&_p]:mt-0 [&_p]:text-lg [&_p]:leading-8 [&_p:last-child]:mb-0">
                        {!! $contentHtml !!}
                    </div>
                @else
                    <div class="rounded-lg bg-white/80 p-6 text-lg leading-8 text-zinc-700 shadow-sm shadow-zinc-950/5 ring-1 ring-zinc-200/70 dark:bg-zinc-900/70 dark:text-zinc-300 dark:ring-zinc-800">
                        {{ __('Sadržaj objave još nije dodan.') }}
                    </div>
                @endif
            </div>

            @if (($previousPost ?? null) || ($nextPost ?? null))
                <nav class="mt-12 grid w-full gap-4 sm:grid-cols-2" aria-label="{{ __('Navigacija objava') }}" data-scroll-reveal>
                    @if ($previousPost ?? null)
                        <a href="{{ $previousPost['url'] }}" class="group flex cursor-pointer items-start gap-3 rounded-xl bg-white/55 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:bg-white hover:shadow-md dark:bg-zinc-900/45 dark:hover:bg-zinc-900">
                            <flux:icon name="arrow-left" class="mt-1 size-4 shrink-0 text-zinc-400 transition group-hover:text-[color:var(--niva-primary-700)] dark:text-zinc-500 dark:group-hover:text-[color:var(--niva-primary-300)]" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $previousPostLabel }}</span>
                                <span class="mt-1 block text-base font-semibold leading-6 text-zinc-950 transition group-hover:text-[color:var(--niva-primary-800)] dark:text-white dark:group-hover:text-[color:var(--niva-primary-200)]">
                                    {{ $previousPost['title'] }}
                                </span>
                            </span>
                        </a>
                    @else
                        <span class="hidden sm:block"></span>
                    @endif

                    @if ($nextPost ?? null)
                        <a href="{{ $nextPost['url'] }}" class="group flex cursor-pointer items-start justify-end gap-3 rounded-xl bg-white/55 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:bg-white hover:shadow-md dark:bg-zinc-900/45 dark:hover:bg-zinc-900 sm:text-right">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $nextPostLabel }}</span>
                                <span class="mt-1 block text-base font-semibold leading-6 text-zinc-950 transition group-hover:text-[color:var(--niva-primary-800)] dark:text-white dark:group-hover:text-[color:var(--niva-primary-200)]">
                                    {{ $nextPost['title'] }}
                                </span>
                            </span>
                            <flux:icon name="arrow-right" class="mt-1 size-4 shrink-0 text-zinc-400 transition group-hover:text-[color:var(--niva-primary-700)] dark:text-zinc-500 dark:group-hover:text-[color:var(--niva-primary-300)]" />
                        </a>
                    @endif
                </nav>
            @endif

            @if ($categories->isNotEmpty() || $tags->isNotEmpty())
                <footer class="mt-10 max-w-3xl" data-scroll-reveal>
                    <div @class([
                        'grid gap-6 rounded-xl bg-white/45 p-5 dark:bg-zinc-900/35',
                        'sm:grid-cols-2' => $categories->isNotEmpty() && $tags->isNotEmpty(),
                    ])>
                        @if ($categories->isNotEmpty())
                            <div>
                                <p class="inline-flex items-center gap-2 text-base font-semibold text-zinc-950 dark:text-white">
                                    <flux:icon name="folder" class="size-4 text-[color:var(--niva-primary-700)] dark:text-[color:var(--niva-primary-300)]" />
                                    {{ __('Kategorije') }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                    @foreach ($categories as $category)
                                        <a href="{{ route('public.organization.posts.taxonomy', ['organizationSlug' => $organization->slug, 'pageSlug' => $page->slug, 'taxonomyKind' => 'kategorija', 'taxonomySlug' => $category->slug]) }}" class="inline-flex cursor-pointer text-base leading-7 text-[color:var(--niva-primary-700)] transition hover:text-[color:var(--niva-primary-800)] dark:text-[color:var(--niva-primary-300)] dark:hover:text-[color:var(--niva-primary-200)]">
                                            {{ $category->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($tags->isNotEmpty())
                            <div>
                                <p class="inline-flex items-center gap-2 text-base font-semibold text-zinc-950 dark:text-white">
                                    <flux:icon name="tag" class="size-4 text-[color:var(--niva-primary-700)] dark:text-[color:var(--niva-primary-300)]" />
                                    {{ __('Oznake') }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                    @foreach ($tags as $tag)
                                        <a href="{{ route('public.organization.page', ['organizationSlug' => $organization->slug, 'pageSlug' => $page->slug, 'oznaka' => $tag->slug]) }}" class="inline-flex cursor-pointer text-base leading-7 text-[color:var(--niva-primary-700)] transition hover:text-[color:var(--niva-primary-800)] dark:text-[color:var(--niva-primary-300)] dark:hover:text-[color:var(--niva-primary-200)]">
                                            #{{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </footer>
            @endif
        </article>
    </main>
</x-layouts.public>
