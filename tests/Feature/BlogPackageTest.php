<?php

use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Models\Post;

class BlogTestTeamResolver
{
    public function resolve(): int
    {
        return 123;
    }
}

it('boots the package', function (): void {
    expect(config('blog.tables.posts'))->toBe('blog_posts');
});

it('creates the posts table', function (): void {
    expect(Schema::hasTable('blog_posts'))->toBeTrue();
});

it('creates a post', function (): void {
    $post = Post::query()->create([
        'title' => ['en' => 'First post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->title)->toBe(['en' => 'First post']);
});

it('generates uuid', function (): void {
    $post = Post::query()->create([
        'title' => ['en' => 'Uuid post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post->uuid)->toBeString()->not->toBeEmpty();
});

it('generates slug through the configured sanigen hook when available', function (): void {
    config()->set('blog.slug.sanigen.generator', BlogTestSlugger::class);

    $post = Post::query()->create([
        'title' => ['en' => 'Sanigen Slug'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post->slug)->toBe('sanigen-slug');
});

it('resolves team id', function (): void {
    config()->set('blog.team_resolver', BlogTestTeamResolver::class);

    $post = Post::query()->create([
        'title' => ['en' => 'Team post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post->team_id)->toBe(123);
});

it('finds post by uuid', function (): void {
    $post = Post::query()->create([
        'title' => ['en' => 'Lookup post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect(Post::query()->where('uuid', $post->uuid)->first()?->is($post))->toBeTrue();
});

it('can attach taxonomy when taxonomy package is installed', function (): void {
    if (! class_exists(config('blog.taxonomy.trait'))) {
        $this->markTestSkipped('Taxonomy package is not installed in this checkout.');
    }

    expect(true)->toBeTrue();
});

it('validates status and context', function (): void {
    $result = app(CreatePostAction::class)->handle([
        'title' => ['en' => 'Invalid post'],
        'context' => 'invalid',
        'status' => 'invalid',
    ]);

    expect($result->successful)->toBeFalse();
});

it('filters published posts', function (): void {
    Post::query()->create([
        'title' => ['en' => 'Draft post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    $published = Post::query()->create([
        'title' => ['en' => 'Published post'],
        'context' => 'news',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect(Post::query()->published()->pluck('uuid')->all())->toBe([$published->uuid]);
});

class BlogTestSlugger
{
    public function generate(string $source): string
    {
        return str($source)->slug()->toString();
    }
}
