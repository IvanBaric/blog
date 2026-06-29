# Blog Project Integration

This document describes the standard admin integration every project should use with `ivanbaric/blog`.

The package owns the reusable post model, post actions, admin post index, admin post form, post taxonomy screen, public post views and public organization-post taxonomy controller. The host project should only wire the admin shell, sidebar links and optional legacy redirects.

## Required Admin Links

Every project should expose these three admin links:

- `Sve objave` -> `route('admin.blog.index')`
- `Kategorije` -> `route('admin.blog.categories')`
- `Oznake` -> `route('admin.blog.tags')`

Recommended sidebar group:

```blade
<flux:sidebar.group expandable icon="newspaper" heading="Objave" class="grid">
    <flux:sidebar.item
        :href="route('admin.blog.index')"
        :current="request()->routeIs('admin.blog.index', 'admin.blog.create', 'admin.blog.edit')"
        wire:navigate
    >
        {{ __('Sve objave') }}
    </flux:sidebar.item>

    <flux:sidebar.item
        :href="route('admin.blog.categories')"
        :current="request()->routeIs('admin.blog.categories')"
        wire:navigate
    >
        {{ __('Kategorije') }}
    </flux:sidebar.item>

    <flux:sidebar.item
        :href="route('admin.blog.tags')"
        :current="request()->routeIs('admin.blog.tags')"
        wire:navigate
    >
        {{ __('Oznake') }}
    </flux:sidebar.item>
</flux:sidebar.group>
```

## Package Routes

The blog package registers its admin routes from `packages/ivanbaric/blog/routes/admin.php`.

Default routes:

```php
route('admin.blog.index');
route('admin.blog.create');
route('admin.blog.edit', ['post' => $post->uuid]);
route('admin.blog.categories');
route('admin.blog.tags');
```

Default URL prefix is `app/blog`, so the standard admin pages are:

- `/app/blog`
- `/app/blog/create`
- `/app/blog/{post}/edit`
- `/app/blog/categories`
- `/app/blog/tags`

Configure the route prefix and name prefix in `config/blog.php` only when a project needs to override the defaults:

```php
'routes' => [
    'admin_name_prefix' => 'admin.blog.',
    'admin_prefix' => 'app/blog',
    'middleware' => ['web', 'auth', 'verified'],
],
```

## Optional Legacy Redirects

If an existing project already has links or browser bookmarks under `/app/posts`, keep only redirects in the host app:

```php
Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->group(function (): void {
        Route::redirect('/posts', '/app/blog')->name('niva.posts.index');
        Route::redirect('/posts/create', '/app/blog/create')->name('niva.posts.create');
        Route::get('/posts/{post}/edit', fn (string $post) => redirect()->route('admin.blog.edit', ['post' => $post]))->name('niva.posts.edit');
        Route::redirect('/posts/categories', '/app/blog/categories')->name('niva.posts.categories');
        Route::redirect('/posts/tags', '/app/blog/tags')->name('niva.posts.tags');
    });
```

New projects should prefer the package routes directly and skip the `niva.posts.*` aliases unless they need backward compatibility.

## Taxonomies

Post categories and tags are handled by the package Livewire component:

```php
IvanBaric\Blog\Livewire\Admin\PostTaxonomies::class
```

The component uses the shared taxonomy package models:

- `IvanBaric\Taxonomy\Models\Taxonomy`
- `IvanBaric\Taxonomy\Models\TaxonomyItem`

It accepts two supported types through route defaults:

- `category` for `Kategorije`
- `tags` for `Oznake`

Unsupported types return `404`.

## Public Views

The package provides reusable public views:

```php
blog::public.posts.index
blog::public.posts.show
blog::public.organization-content.post
blog::public.organization-content.post-taxonomy
```

The organization taxonomy controller lives in the package:

```php
IvanBaric\Blog\Http\Controllers\Public\OrganizationPostTaxonomyController
```

The host project may still define public routes because slugs, organization models and page routing are project-specific.

## Responsibility Split

Keep this responsibility split:

- `ivanbaric/blog` owns post storage, post list, post form, post taxonomy admin, post actions, post events and reusable public post views.
- `ivanbaric/taxonomy` owns generic taxonomy models and taxonomy persistence.
- `ivanbaric/gallery` owns image/media attachments used by blog posts.
- The project owns the admin shell, sidebar placement and project-specific public route definitions.

Do not create separate post modules for news, projects, announcements or events unless the project truly needs different persistence. Use the `context` column from the blog package for that distinction.
