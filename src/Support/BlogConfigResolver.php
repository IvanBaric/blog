<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Support\ConfigResolver;

final class BlogConfigResolver
{
    /** @return class-string<Post> */
    public static function postModel(): string
    {
        return app(ConfigResolver::class)->model(
            key: 'blog.models.post',
            default: Post::class,
            expectedType: Post::class,
        );
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        $default = config('auth.providers.users.model', User::class);
        $default = is_string($default) && is_a($default, Model::class, true)
            ? $default
            : User::class;

        return app(ConfigResolver::class)->model(
            key: 'blog.models.user',
            default: $default,
            expectedType: Model::class,
        );
    }

    public static function postsTable(): string
    {
        return app(ConfigResolver::class)->table(
            key: 'blog.tables.posts',
            default: 'blog_posts',
        );
    }

    /** @return class-string<Model>|null */
    public static function publicOrganizationModel(): ?string
    {
        return self::optionalModel('blog.public_organization.organization_model');
    }

    /** @return class-string<Model>|null */
    public static function publicOrganizationPageModel(): ?string
    {
        return self::optionalModel('blog.public_organization.page_model');
    }

    /** @return class-string<Model>|null */
    public static function pagesSectionModel(): ?string
    {
        $pagesResolver = 'IvanBaric\\Pages\\Support\\PagesConfigResolver';

        if (class_exists($pagesResolver)) {
            return $pagesResolver::sectionModel();
        }

        return self::optionalModel('pages.models.section');
    }

    public static function pagesTable(): string
    {
        $pagesResolver = 'IvanBaric\\Pages\\Support\\PagesConfigResolver';

        if (class_exists($pagesResolver)) {
            return $pagesResolver::pagesTable();
        }

        return app(ConfigResolver::class)->table(
            key: 'pages.tables.pages',
            default: 'pages',
        );
    }

    /** @return class-string<Model>|null */
    private static function optionalModel(string $key): ?string
    {
        $configured = config($key);

        if ($configured === null || $configured === '') {
            return null;
        }

        return app(ConfigResolver::class)->model(
            key: $key,
            default: Post::class,
            expectedType: Model::class,
        );
    }
}
