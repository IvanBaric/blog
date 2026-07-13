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

`config/blog.php` contains only package-level choices that are consumed at runtime: model and table overrides, contexts, statuses, routes, public organization integration, pagination, locale fallback, media visibility, admin layout, permissions and supported UI features. Tenant, actor, UUID, slug normalization and locale context come from Corexis.

The default admin routes use the `app/blog` prefix:

```php
route('admin.blog.index');
route('admin.blog.edit', ['post' => $post->uuid]);
route('admin.blog.categories');
route('admin.blog.tags');
```

## Project Integration

For the standard admin integration used in every project, see:

- [`docs/project-integration.md`](docs/project-integration.md)

That document covers the required `Sve objave`, `Kategorije` and `Oznake` sidebar links, the blog admin routes, optional project aliases such as `niva.posts.*`, the package taxonomy screen and reusable public post views.

## Usage

```php
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Actions\PublishPostAction;
use IvanBaric\Blog\Models\Post;

$result = app(CreatePostAction::class)->handle([
    'title' => ['en' => 'Launch news'],
    'excerpt' => ['en' => 'Short summary'],
    'content' => ['en' => 'Full content'],
    'context' => 'news',
    'status' => 'draft',
]);

$post = $result->data;
app(PublishPostAction::class)->handle($post);

Post::query()->published()->context('news')->ordered()->get();
Post::query()->where('uuid', $uuid)->firstOrFail();
```

The route key is `uuid`. Admin and Livewire actions use `uuid`; `id` is not used for public lookup.

## Model Resolution

`IvanBaric\Blog\Support\BlogModels` is the single model resolver for package internals. `blog.models.post` must extend the package `Post` model, while `blog.models.user` falls back to the configured Laravel authentication provider. Relations and Actions resolve these classes through `BlogModels`; consumers can therefore replace models without editing package code.

## Integrations

Taxonomy persistence and tenant-aware assignments come from `ivanbaric/taxonomy`. The blog package uses its `HasTaxonomies` concern and models directly instead of exposing duplicate taxonomy configuration.

SEO storage is not implemented here. The model only exposes SEO defaults and canonical URL hooks for a dedicated SEO package. The `meta` column remains general extension data and is not an SEO replacement.

Slug generation, tenant-scoped uniqueness and suffixes are provided by Corexis. A shared normalizer such as Sanigen can be configured once in `config/corexis.php`:

```php
'slug' => [
    'normalizer' => App\Support\SanigenSlugGenerator::class,
    'normalizer_method' => 'generate',
],
```

Tenant ownership uses `IvanBaric\Corexis\Contracts\TenantResolver`. Configure its concrete implementation once in `config/corexis.php`; in a host application that implementation may live at `App\Resolvers\TeamResolver`. The blog package does not define or configure a second tenant resolver.

Actor and locale context follow the same ecosystem rule through `corexis_actor_id()` and `corexis_locale_code()`. Public organization/page model classes and public route names are configurable under `blog.public_organization`.

Media/gallery logic is not duplicated. The package uses `ivanbaric/gallery` through `HasGalleries`; Media Library is the only source for featured images.

Admin UI shell, sidebar, layout, cards and base structure are not duplicated. The Livewire views use Flux components and render inside `blog.admin_ui.layout`.

## Architecture

State-changing operations should use the package actions in `src/Actions`.

The current write flow is:

```text
Livewire Component -> PostFormState -> Action -> Corexis ActionResult -> Domain Event -> Listener
```

All actions return `IvanBaric\Corexis\Data\ActionResult`; the blog package does not maintain a duplicate result DTO.

The supported write Actions are `CreatePostAction`, `SavePostAction`, `UpdatePostAction`, `PublishPostAction`, `ArchivePostAction`, `ToggleFeaturedPostAction`, `DeletePostAction` and the taxonomy item Actions. Each Action authorizes on the server, re-resolves tenant-owned records, and locks state transitions inside a database transaction where concurrent changes matter.

`Post` owns the domain transitions used by those Actions:

- `publish()` publishes immediately or at the supplied date.
- `unpublish()` returns the post to draft and removes its featured flag.
- `archive()` archives and unfeatures the post.
- `markAsFeatured()` succeeds only for a published post.
- `unmarkAsFeatured()` removes the featured flag.

Publishing requires meaningful rich-text content. Archived posts must first return to draft, only published posts can be featured, and published posts must be archived before deletion. Applications should call Actions for user-driven writes so authorization, locking, validation and events are not bypassed.

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
