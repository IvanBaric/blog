<?php

namespace IvanBaric\Blog;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use IvanBaric\Blog\Livewire\Admin\PostForm;
use IvanBaric\Blog\Livewire\Admin\PostIndex;
use IvanBaric\Blog\Livewire\Admin\PostTaxonomies;
use IvanBaric\Blog\Livewire\PublicSite\PostSingleActions;
use IvanBaric\Blog\Livewire\PublicSite\PostSingleContent;
use Livewire\Livewire;

final class BlogServiceProvider extends ServiceProvider
{
    /** @var array<int, string> */
    private const REPLACE_CONFIG_KEYS = [
        'contexts',
        'permissions',
        'statuses',
    ];

    public function register(): void
    {
        $this->mergeConfigRecursivelyFrom(__DIR__.'/../config/blog.php', 'blog');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'blog');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Livewire::component('blog.admin.posts.index', PostIndex::class);
        Livewire::component('blog.admin.posts.form', PostForm::class);
        Livewire::component('blog.admin.post-taxonomies', PostTaxonomies::class);
        Livewire::component('blog.public.post-single-actions', PostSingleActions::class);
        Livewire::component('blog.public.post-single-content', PostSingleContent::class);

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

    private function mergeConfigRecursivelyFrom(string $path, string $key): void
    {
        $defaults = require $path;
        $configured = $this->app['config']->get($key, []);

        $this->app['config']->set($key, $this->mergeConfigValues($defaults, $configured, root: true));
    }

    /**
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $configured
     * @return array<mixed>
     */
    private function mergeConfigValues(array $defaults, array $configured, bool $root = false): array
    {
        foreach ($configured as $key => $value) {
            if ($root && is_string($key) && in_array($key, self::REPLACE_CONFIG_KEYS, true)) {
                $defaults[$key] = $value;

                continue;
            }

            if (
                is_array($value)
                && array_key_exists($key, $defaults)
                && is_array($defaults[$key])
                && ! array_is_list($value)
                && ! array_is_list($defaults[$key])
            ) {
                $defaults[$key] = $this->mergeConfigValues($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
