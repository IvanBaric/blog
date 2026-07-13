<?php

namespace IvanBaric\Blog\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Corexis\Concerns\BelongsToTenant;
use IvanBaric\Corexis\Concerns\HasLockVersion;
use IvanBaric\Corexis\Concerns\HasUniqueSlug;
use IvanBaric\Corexis\Concerns\HasUuid;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Taxonomy\Traits\HasTaxonomies;

/**
 * @property int $id
 * @property int|null $team_id
 * @property int|null $user_id
 * @property int|null $updated_user_id
 * @property string $uuid
 * @property string $slug
 * @property array<string, mixed>|string $title
 * @property array<string, mixed>|string|null $excerpt
 * @property array<string, mixed>|string|null $content
 * @property string $context
 * @property string $status
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
    use BelongsToTenant, HasFactory, HasGalleries, HasLockVersion, HasTaxonomies, HasUniqueSlug, HasUuid, SoftDeletes;

    public const FEATURED_IMAGE_COLLECTION = 'featured_image';

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return BlogConfigResolver::postsTable();
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (! $post->getAttribute('context')) {
                $post->setAttribute('context', config('blog.default_context', 'blog'));
            }

            if (! $post->getAttribute('status')) {
                $post->setAttribute('status', config('blog.default_status', 'draft'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'updated_user_id' => 'integer',
            'title' => 'array',
            'excerpt' => 'array',
            'content' => 'array',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'meta' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(BlogModels::user(), 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(BlogModels::user(), 'updated_user_id');
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
        $query->where('is_featured', true)
            ->where('status', 'published');
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('created_at');
    }

    public function slugSource(): string
    {
        return $this->localized('title');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
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
            'is_featured' => false,
        ])->save();
    }

    public function archive(int|string|null $archiverId = null): bool
    {
        $attributes = [
            'status' => 'archived',
            'is_featured' => false,
        ];

        if ($archiverId !== null) {
            $attributes['updated_user_id'] = $archiverId;
        }

        return $this->forceFill($attributes)->save();
    }

    public function markAsFeatured(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return $this->forceFill(['is_featured' => true])->save();
    }

    public function unmarkAsFeatured(): bool
    {
        return $this->forceFill(['is_featured' => false])->save();
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return (string) $value;
        }

        $locale ??= self::currentLocaleCode();
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
            'image' => $this->seoImageUrl(),
        ];
    }

    public function seoCanonicalUrl(): ?string
    {
        $routeName = (string) config('blog.seo.canonical_route_name', 'posts.show');

        return $this->exists && $routeName !== '' && Route::has($routeName)
            ? route($routeName, $this)
            : null;
    }

    public function seoImageUrl(): ?string
    {
        return $this->featuredImageUrl('large');
    }

    public function featuredImageUrl(string $conversion = 'large'): ?string
    {
        return $this->galleryImageUrl(self::FEATURED_IMAGE_COLLECTION, $conversion);
    }

    public function shouldBeIndexed(): bool
    {
        return $this->isPublished();
    }
}
