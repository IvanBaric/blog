# IvanBaric Blog

Reusable context-based posts for Laravel 13 and PHP 8.3+.

The package provides one `IvanBaric\Blog\Models\Post` model backed by `blog_posts`. Contexts such as blog, news, events, projects, fairs, competitions, awards and announcements are represented by the `context` column, not separate models.

## Installation

```bash
composer require ivanbaric/blog
php artisan vendor:publish --tag=blog-config
php artisan vendor:publish --tag=blog-migrations
php artisan migrate
```

Optional publish tags:

```bash
php artisan vendor:publish --tag=blog-views
php artisan vendor:publish --tag=blog-translations
```

## Configuration

`config/blog.php` controls table names, model overrides, team resolver, contexts, statuses, route names, middleware, pagination, translatable fields, taxonomy, SEO, media/gallery, admin-ui and feature flags.

The default admin routes are:

```php
route('admin.blog.index');
route('admin.blog.create');
route('admin.blog.edit', ['post' => $post->uuid]);
```

## Usage

```php
use IvanBaric\Blog\Models\Post;

$post = Post::query()->create([
    'title' => ['en' => 'Launch news'],
    'excerpt' => ['en' => 'Short summary'],
    'content' => ['en' => 'Full content'],
    'context' => 'news',
    'status' => 'draft',
]);

$post->publish();
$post->markAsFeatured();

Post::query()->published()->context('news')->ordered()->get();
Post::query()->where('uuid', $uuid)->firstOrFail();
```

The route key is `uuid`. Admin and Livewire actions use `uuid`; `id` is not used for public lookup.

## Integrations

Taxonomy is not implemented here. Configure `blog.taxonomy.trait` for your taxonomy package, for example `IvanBaric\Taxonomy\Traits\HasTaxonomies`, and attach taxonomy through that package.

SEO is not implemented here. Configure `blog.seo.trait` for your SEO package, for example `IvanBaric\Seo\Concerns\HasSeo`, and store SEO data through that package. The `meta` column is only for extensibility and is not an SEO replacement.

Slug generation is delegated to Sanigen when a configured generator is available:

```php
'slug' => [
    'sanigen' => [
        'generator' => App\Support\SanigenSlugGenerator::class,
        'method' => 'generate',
    ],
],
```

Team ownership uses `App\Resolvers\TeamResolver` automatically when the class exists. The resolver may expose `resolve()`, `currentTeamId()`, `teamId()` or `id()`.

Media/gallery logic is not duplicated. The package stores an optional `featured_image` string and exposes media/gallery config hooks for the existing gallery package.

Admin UI shell, sidebar, layout, cards and base structure are not duplicated. The Livewire views use Flux components and render inside `blog.admin_ui.layout`.

## Architecture

State-changing operations should use the package actions in `src/Actions`.

The current write flow is:

```text
Livewire Component -> PostFormState -> Action -> Blog ActionResult/Corexis ActionResult adapter -> Domain Event -> Listener
```

The existing `IvanBaric\Blog\Data\ActionResult` class remains for backwards compatibility. Use `toCorexis()` when consumers need `IvanBaric\Corexis\Data\ActionResult`.

Successful write actions dispatch small after-commit domain events:

- `PostCreated`
- `PostUpdated`
- `PostDeleted`
- `PostPublished`
- `PostUnpublished`
- `PostArchived`
- `PostFeatured`
- `PostUnfeatured`
- `PostSaved`

`PostSaved` is the integration boundary for save-form side effects. SEO, audit, taxonomy or media packages should listen to blog events instead of being called directly from blog actions.

## Tests

```bash
cd packages/ivanbaric/blog
composer install
vendor/bin/pest
```
