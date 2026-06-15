<?php

namespace IvanBaric\Blog\Support;

use Illuminate\Support\Str;
use IvanBaric\Blog\Models\Post;

final class SlugGenerator
{
    public function generate(Post $post, string $source): string
    {
        $slug = $this->generateWithSanigen($source) ?? Str::slug($source);
        $slug = $slug !== '' ? $slug : (string) Str::uuid();

        return $this->unique($post, $slug);
    }

    private function generateWithSanigen(string $source): ?string
    {
        $generator = config('blog.slug.sanigen.generator');
        $method = config('blog.slug.sanigen.method', 'generate');

        if (is_string($generator) && class_exists($generator) && method_exists($generator, $method)) {
            return (string) app($generator)->{$method}($source);
        }

        foreach ([
            'IvanBaric\\Sanigen\\Facades\\Sanigen',
            'IvanBaric\\Sanigen\\Support\\Sanigen',
            'IvanBaric\\Sanigen\\Sanigen',
        ] as $class) {
            if (class_exists($class) && method_exists($class, 'slug')) {
                return (string) $class::slug($source);
            }
        }

        return null;
    }

    private function unique(Post $post, string $slug): string
    {
        $base = $slug;
        $counter = 2;

        while ($this->exists($post, $slug)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function exists(Post $post, string $slug): bool
    {
        $query = $post->newQuery()->where('slug', $slug);

        if (config('blog.slug.scoped_to_team', true)) {
            $post->team_id === null
                ? $query->whereNull('team_id')
                : $query->where('team_id', $post->team_id);
        }

        if ($post->exists) {
            $query->whereKeyNot($post->getKey());
        }

        return $query->exists();
    }
}
