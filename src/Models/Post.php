<?php

namespace IvanBaric\Blog\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use IvanBaric\Blog\Support\SlugGenerator;
use IvanBaric\Blog\Support\TeamResolver;
use IvanBaric\Taxonomy\Traits\HasTaxonomies;

/**
 * @property int $id
 * @property int|null $team_id
 * @property string $uuid
 * @property string $slug
 * @property array<string, mixed>|string $title
 * @property array<string, mixed>|string|null $excerpt
 * @property array<string, mixed>|string|null $content
 * @property string $context
 * @property string $status
 * @property string|null $featured_image
 * @property Carbon|null $published_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property int $sort_order
 * @property bool $is_featured
 * @property array<string, mixed>|null $meta
 */
class Post extends Model
{
    use HasFactory, HasTaxonomies, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('blog.tables.posts', 'blog_posts');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            $attributes = $post->getAttributes();

            if (! ($attributes['context'] ?? null)) {
                $post->setAttribute('context', config('blog.default_context', 'blog'));
            }

            if (! ($attributes['status'] ?? null)) {
                $post->setAttribute('status', config('blog.default_status', 'draft'));
            }

            if (! ($attributes['team_id'] ?? null)) {
                $post->setAttribute('team_id', app(TeamResolver::class)->resolve());
            }
        });

        static::saving(function (self $post): void {
            if (! $post->slug || $post->isDirty('title')) {
                $post->slug = app(SlugGenerator::class)->generate($post, $post->localized('title'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'content' => 'array',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'meta' => 'array',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopeContext(Builder $query, string $context): void
    {
        $query->where('context', $context);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('created_at');
    }

    #[Scope]
    protected function forTeam(Builder $query, ?int $teamId): void
    {
        $teamId === null ? $query->whereNull('team_id') : $query->where('team_id', $teamId);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(?Carbon $publishedAt = null): bool
    {
        return $this->forceFill([
            'status' => 'published',
            'published_at' => $publishedAt ?? now(),
        ])->save();
    }

    public function unpublish(): bool
    {
        return $this->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();
    }

    public function markAsFeatured(): bool
    {
        return $this->forceFill(['is_featured' => true])->save();
    }

    public function unmarkAsFeatured(): bool
    {
        return $this->forceFill(['is_featured' => false])->save();
    }

    public function getSlugSourceAttribute(): string
    {
        return $this->localized('title');
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return (string) $value;
        }

        $locale ??= static::currentLocaleCode();
        $fallback = config('blog.translatable.default_locale') ?: config('app.fallback_locale', 'en');

        return (string) ($value[$locale] ?? $value[$fallback] ?? reset($value) ?: '');
    }

    private static function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }

    /**
     * @return array<string, mixed>
     */
    public function seoDefaults(): array
    {
        return [
            'title' => $this->localized('title'),
            'description' => str($this->localized('content'))->stripTags()->squish()->limit(160)->toString(),
            'image' => $this->featured_image,
        ];
    }

    public function seoCanonicalUrl(): ?string
    {
        return $this->exists ? route('posts.show', $this) : null;
    }

    public function seoImageUrl(): ?string
    {
        return $this->featured_image;
    }

    public function shouldBeIndexed(): bool
    {
        return $this->isPublished();
    }
}
