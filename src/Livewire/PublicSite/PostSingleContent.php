<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\PublicSite;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Blog\Support\PublishablePostContent;
use IvanBaric\Blog\Support\RichTextSanitizer;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

final class PostSingleContent extends Component
{
    private ?Post $resolvedPost = null;

    private bool $postWasResolved = false;

    private ?Model $resolvedSection = null;

    private bool $sectionWasResolved = false;

    #[Locked]
    public string $postUuid = '';

    #[Locked]
    public ?int $teamId = null;

    #[Locked]
    public ?string $sectionUuid = null;

    #[Locked]
    public string $organizationSlug = '';

    #[Locked]
    public string $pageSlug = '';

    #[Locked]
    public string $backUrl = '';

    #[Locked]
    public string $backLabel = '';

    #[Locked]
    public string $previousPostLabel = '';

    #[Locked]
    public string $nextPostLabel = '';

    /** @var array{title?: string, url?: string}|null */
    #[Locked]
    public ?array $previousPost = null;

    /** @var array{title?: string, url?: string}|null */
    #[Locked]
    public ?array $nextPost = null;

    /** @var list<array{name: string, slug: string}> */
    #[Locked]
    public array $categories = [];

    /** @var list<array{name: string, slug: string}> */
    #[Locked]
    public array $tags = [];

    public function mount(
        Post $post,
        ?Model $section = null,
        string $organizationSlug = '',
        string $pageSlug = '',
        string $backUrl = '',
        string $backLabel = '',
        string $previousPostLabel = '',
        string $nextPostLabel = '',
        ?array $previousPost = null,
        ?array $nextPost = null,
        mixed $categories = [],
        mixed $tags = [],
    ): void {
        $this->resolvedPost = $post->isPublished() ? $post : null;
        $this->postWasResolved = true;
        $this->resolvedSection = $section;
        $this->sectionWasResolved = true;
        $this->postUuid = (string) $post->getAttribute('uuid');
        $this->teamId = is_numeric($post->getAttribute('team_id')) ? (int) $post->getAttribute('team_id') : null;
        $this->sectionUuid = $section ? (string) $section->getAttribute('uuid') : null;
        $this->organizationSlug = $organizationSlug;
        $this->pageSlug = $pageSlug;
        $this->backUrl = $backUrl;
        $this->backLabel = $backLabel !== '' ? $backLabel : __('Natrag na objave');
        $this->previousPostLabel = $previousPostLabel !== '' ? $previousPostLabel : __('Prethodna objava');
        $this->nextPostLabel = $nextPostLabel !== '' ? $nextPostLabel : __('Sljedeća objava');
        $this->previousPost = $this->navigationPost($previousPost);
        $this->nextPost = $this->navigationPost($nextPost);
        $this->categories = $this->taxonomyItems($categories);
        $this->tags = $this->taxonomyItems($tags);
    }

    #[On('single-post-layout-cycled')]
    public function refreshSingleLayout(?string $sectionUuid = null): void
    {
        // The render method reads the latest section settings after the event.
    }

    public function render(): View
    {
        $post = $this->post();
        $section = $this->section();

        abort_if($post === null, 404);
        abort_unless(PublishablePostContent::isPresent($post->content), 404);

        $content = str($post->localized('content'))->trim()->toString();
        $excerpt = str($post->localized('excerpt'))->stripTags()->squish()->toString();
        $wordMatches = [];
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($content), $wordMatches);

        $image = $post->featuredImageUrl('xlarge');

        $title = $post->localized('title');
        $gallery = $post->gallery('images');
        $galleryMedia = $gallery ? $gallery->getMedia('images')->values() : collect();
        $galleryPhotos = $galleryMedia
            ->map(function (mixed $media, int $index) use ($title, $gallery): ?array {
                $imageUrl = $this->mediaUrl($media, ['large']);

                if (! $imageUrl) {
                    return null;
                }

                $fallbackAlt = ($gallery?->displayTitle() ?: $title ?: __('Galerija objave')).' '.__('fotografija').' '.($index + 1);
                $caption = trim((string) ($media->getCustomProperty('caption', '') ?: $media->getCustomProperty('title', '') ?: ''));
                $alt = $media->altText($fallbackAlt);

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

        return view('blog::livewire.public-site.post-single-content', [
            'post' => $post,
            'section' => $section,
            'singleLayout' => $this->singleLayout($section),
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
            'contentHtml' => $this->contentHtml($content),
            'dateLabel' => $post->published_at?->copy()->locale(corexis_locale_code() ?: config('app.locale', 'en'))->translatedFormat('j. F Y.'),
            'machineDate' => $post->published_at?->toDateString(),
            'authorName' => data_get($post, 'author.name') ?: data_get($post->meta, 'author_name') ?: data_get($post->meta, 'author'),
            'readingMinutes' => max(1, (int) ceil(count($wordMatches[0]) / 180)),
            'mediaSlides' => $mediaSlides,
            'mediaCountLabel' => trans_choice(':count fotografija|:count fotografije|:count fotografija', $mediaSlides->count(), ['count' => $mediaSlides->count()]),
            'galleryCarouselName' => 'post-gallery-carousel-'.(string) ($post->uuid ?? $post->id),
            'categoryItems' => collect($this->categories),
            'tagItems' => collect($this->tags),
        ]);
    }

    private function contentHtml(string $content): string
    {
        return app(RichTextSanitizer::class)->sanitize($content);
    }

    private function mediaUrl(mixed $media, array $preferred): ?string
    {
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
    }

    private function singleLayout(?Model $section): string
    {
        $layout = (string) data_get($section?->getAttribute('settings'), 'single_layout', 'classic');

        return in_array($layout, ['classic', 'hero', 'compact', 'cover', 'sidebar'], true) ? $layout : 'classic';
    }

    /** @return array{title: string, url: string}|null */
    private function navigationPost(?array $post): ?array
    {
        $title = trim((string) data_get($post, 'title'));
        $url = trim((string) data_get($post, 'url'));

        if ($title === '' || $url === '') {
            return null;
        }

        return ['title' => $title, 'url' => $url];
    }

    /** @return list<array{name: string, slug: string}> */
    private function taxonomyItems(mixed $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): array => [
                'name' => trim((string) data_get($item, 'name')),
                'slug' => trim((string) data_get($item, 'slug')),
            ])
            ->filter(fn (array $item): bool => $item['name'] !== '' && $item['slug'] !== '')
            ->values()
            ->all();
    }

    private function post(): ?Post
    {
        if ($this->postWasResolved) {
            return $this->resolvedPost;
        }

        $this->postWasResolved = true;

        if ($this->postUuid === '' || $this->teamId === null) {
            return null;
        }

        $model = BlogModels::post();

        return $this->resolvedPost = $model::query()
            ->forTenant($this->teamId)
            ->with(['author', 'galleries.media'])
            ->published()
            ->where('uuid', $this->postUuid)
            ->first();
    }

    private function section(): ?Model
    {
        if ($this->sectionWasResolved) {
            return $this->resolvedSection;
        }

        $this->sectionWasResolved = true;

        if (! $this->sectionUuid || $this->teamId === null) {
            return null;
        }

        $sectionModel = BlogConfigResolver::pagesSectionModel();

        if ($sectionModel === null) {
            return null;
        }

        return $this->resolvedSection = $sectionModel::query()
            ->forTenant($this->teamId)
            ->where('uuid', $this->sectionUuid)
            ->first();
    }
}
