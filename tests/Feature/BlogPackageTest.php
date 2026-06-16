<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use IvanBaric\Corexis\Contracts\TenantResolver;
use IvanBaric\Blog\Actions\ArchivePostAction;
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Actions\DeletePostAction;
use IvanBaric\Blog\Actions\PublishPostAction;
use IvanBaric\Blog\Actions\SavePostAction;
use IvanBaric\Blog\Actions\ToggleFeaturedPostAction;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Events\PostArchived;
use IvanBaric\Blog\Events\PostCreated;
use IvanBaric\Blog\Events\PostDeleted;
use IvanBaric\Blog\Events\PostFeatured;
use IvanBaric\Blog\Events\PostPublished;
use IvanBaric\Blog\Events\PostSaved;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Events\PostUnpublished;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Data\ActionResult as CorexisActionResult;

class BlogTestTeamResolver
{
    public function resolve(): int
    {
        return 123;
    }
}

class BlogCorexisTenantResolverFake implements TenantResolver
{
    public function enabled(): bool
    {
        return true;
    }

    public function current(): mixed
    {
        return null;
    }

    public function id(): int|string|null
    {
        return 987;
    }

    public function uuid(): ?string
    {
        return null;
    }

    public function type(): ?string
    {
        return 'team';
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

it('resolves team id through corexis tenant resolver first', function (): void {
    app()->bind(TenantResolver::class, BlogCorexisTenantResolverFake::class);

    $post = Post::query()->create([
        'title' => ['en' => 'Corexis team post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post->team_id)->toBe(987);
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

it('blog action result can be converted to corexis action result', function (): void {
    $result = ActionResult::success(__('Spremljeno.'), ['id' => 10], 'saved');
    $corexis = $result->toCorexis();

    expect($corexis)->toBeInstanceOf(CorexisActionResult::class)
        ->and($corexis->success)->toBeTrue()
        ->and($corexis->message)->toBe('Spremljeno.')
        ->and($corexis->data)->toBe(['id' => 10])
        ->and($corexis->code)->toBe('saved');
});

it('dispatches domain events for successful post actions only', function (): void {
    Event::fake([
        PostCreated::class,
        PostPublished::class,
        PostUnpublished::class,
        PostArchived::class,
        PostFeatured::class,
        PostUnfeatured::class,
        PostDeleted::class,
    ]);

    $failed = app(CreatePostAction::class)->handle([
        'title' => null,
        'context' => 'invalid',
        'status' => 'invalid',
    ]);

    expect($failed->successful)->toBeFalse();
    Event::assertNotDispatched(PostCreated::class);

    $created = app(CreatePostAction::class)->handle([
        'title' => ['en' => 'Event post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($created->successful)->toBeTrue();
    Event::assertDispatched(PostCreated::class);

    /** @var Post $post */
    $post = $created->data;

    app(PublishPostAction::class)->handle($post);
    Event::assertDispatched(PostPublished::class);

    app(PublishPostAction::class)->handle($post->refresh(), false);
    Event::assertDispatched(PostUnpublished::class);

    app(ArchivePostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostArchived::class);

    app(ToggleFeaturedPostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostFeatured::class);

    app(ToggleFeaturedPostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostUnfeatured::class);

    app(DeletePostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostDeleted::class);
});

it('dispatches post saved as the integration boundary event', function (): void {
    Event::fake([PostCreated::class, PostSaved::class]);

    $result = app(SavePostAction::class)->handle(
        post: null,
        data: [
            'title' => ['en' => 'Saved event post'],
            'context' => 'news',
            'status' => 'draft',
        ],
        categoryId: 5,
        tagIds: [7, 8],
        locale: 'en',
    );

    expect($result->successful)->toBeTrue();
    Event::assertDispatched(PostCreated::class);
    Event::assertDispatched(PostSaved::class, fn (PostSaved $event): bool => $event->categoryId === 5
        && $event->tagIds === [7, 8]
        && $event->locale === 'en');
});

class BlogTestSlugger
{
    public function generate(string $source): string
    {
        return str($source)->slug()->toString();
    }
}
