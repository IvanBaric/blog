<?php

namespace IvanBaric\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use IvanBaric\Blog\Livewire\Admin\PostForm;
use IvanBaric\Blog\Livewire\Admin\PostIndex;
use Livewire\Livewire;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blog.php', 'blog');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'blog');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Livewire::component('blog::admin.posts.index', PostIndex::class);
        Livewire::component('blog::admin.posts.form', PostForm::class);

        if (config('blog.features.admin_routes', true)) {
            $this->loadAdminRoutes();
        }

        $this->publishes([
            __DIR__.'/../config/blog.php' => config_path('blog.php'),
        ], 'blog-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'blog-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/blog'),
        ], 'blog-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/blog'),
        ], 'blog-translations');
    }

    private function loadAdminRoutes(): void
    {
        Route::middleware(config('blog.routes.middleware', ['web', 'auth']))
            ->prefix(config('blog.routes.admin_prefix', 'admin/blog'))
            ->name(config('blog.routes.admin_name_prefix', 'admin.blog.'))
            ->group(__DIR__.'/../routes/admin.php');
    }
}
