<?php

namespace IvanBaric\Blog\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\BlogServiceProvider;
use IvanBaric\Blog\Tests\Fixtures\User;
use IvanBaric\Corexis\CorexisServiceProvider;
use IvanBaric\Taxonomy\TaxonomyServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CorexisServiceProvider::class,
            TaxonomyServiceProvider::class,
            LivewireServiceProvider::class,
            BlogServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        $this->loadMigrationsFrom(__DIR__.'/../../taxonomy/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
