<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Actions\ArchivePostAction;
use IvanBaric\Blog\Actions\CreatePostAction;
use IvanBaric\Blog\Actions\DeletePostAction;
use IvanBaric\Blog\Actions\PublishPostAction;
use IvanBaric\Blog\Actions\SavePostAction;
use IvanBaric\Blog\Actions\ToggleFeaturedPostAction;
use IvanBaric\Blog\Actions\UpdatePostAction;
use IvanBaric\Blog\Events\PostArchived;
use IvanBaric\Blog\Events\PostCreated;
use IvanBaric\Blog\Events\PostDeleted;
use IvanBaric\Blog\Events\PostFeatured;
use IvanBaric\Blog\Events\PostPublished;
use IvanBaric\Blog\Events\PostSaved;
use IvanBaric\Blog\Events\PostUnfeatured;
use IvanBaric\Blog\Events\PostUnpublished;
use IvanBaric\Blog\Events\PostUpdated;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Tests\Fixtures\User;
use IvanBaric\Corexis\Contracts\TenantResolver;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

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
    expect(Schema::hasTable('blog_posts'))->toBeTrue()
        ->and(Schema::hasColumn('blog_posts', 'featured_image'))->toBeFalse();
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
    config()->set('corexis.slug.normalizer', BlogTestSlugger::class);

    $post = Post::query()->create([
        'title' => ['en' => 'Sanigen Slug'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($post->slug)->toBe('configured-sanigen-slug');
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

it('stores the authenticated user as post author', function (): void {
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.test',
        'password' => 'password',
    ]);

    $this->actingAs($user);

    $result = app(CreatePostAction::class)->handle([
        'title' => ['en' => 'Author post'],
        'context' => 'news',
        'status' => 'draft',
    ]);
    $post = $result->data;

    expect($result->success)->toBeTrue()
        ->and($post->user_id)->toBe($user->id)
        ->and($post->author?->is($user))->toBeTrue();
});

it('finds post by uuid', function (): void {
    $post = Post::query()->create([
        'title' => ['en' => 'Lookup post'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect(Post::query()->where('uuid', $post->uuid)->first()?->is($post))->toBeTrue();
});

it('validates status and context', function (): void {
    $result = app(CreatePostAction::class)->handle([
        'title' => ['en' => 'Invalid post'],
        'context' => 'invalid',
        'status' => 'invalid',
    ]);

    expect($result->success)->toBeFalse();
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

it('archives a post through its domain method', function (): void {
    $post = Post::query()->create([
        'title' => ['en' => 'Archived post'],
        'context' => 'news',
        'status' => 'published',
        'published_at' => now(),
        'is_featured' => true,
    ]);

    expect($post->archive(321))->toBeTrue()
        ->and($post->isArchived())->toBeTrue()
        ->and($post->is_featured)->toBeFalse()
        ->and($post->updated_user_id)->toBe(321);
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

    expect($failed->success)->toBeFalse();
    Event::assertNotDispatched(PostCreated::class);

    $created = app(CreatePostAction::class)->handle([
        'title' => ['en' => 'Event post'],
        'content' => ['en' => 'Event post content.'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    expect($created->success)->toBeTrue();
    Event::assertDispatched(PostCreated::class);

    /** @var Post $post */
    $post = $created->data;

    app(PublishPostAction::class)->handle($post);
    Event::assertDispatched(PostPublished::class);

    app(PublishPostAction::class)->handle($post->refresh(), false);
    Event::assertDispatched(PostUnpublished::class);

    app(PublishPostAction::class)->handle($post->refresh());
    app(ToggleFeaturedPostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostFeatured::class);

    app(ArchivePostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostArchived::class);
    Event::assertDispatched(PostUnfeatured::class);

    app(DeletePostAction::class)->handle($post->refresh());
    Event::assertDispatched(PostDeleted::class);
});

it('dispatches post saved as the integration boundary event', function (): void {
    Event::fake([PostCreated::class, PostSaved::class]);

    $categoryTaxonomy = Taxonomy::query()->create([
        'name' => 'Categories',
        'type' => 'category',
        'is_filterable' => true,
    ]);
    $tagTaxonomy = Taxonomy::query()->create([
        'name' => 'Tags',
        'type' => 'tags',
        'is_filterable' => true,
        'is_multiple' => true,
    ]);
    $category = TaxonomyItem::query()->create([
        'taxonomy_id' => $categoryTaxonomy->id,
        'name' => 'News',
    ]);
    $firstTag = TaxonomyItem::query()->create([
        'taxonomy_id' => $tagTaxonomy->id,
        'name' => 'Workshop',
    ]);
    $secondTag = TaxonomyItem::query()->create([
        'taxonomy_id' => $tagTaxonomy->id,
        'name' => 'Students',
    ]);

    $result = app(SavePostAction::class)->handle(
        post: null,
        data: [
            'title' => ['en' => 'Saved event post'],
            'context' => 'news',
            'status' => 'draft',
        ],
        categoryId: $category->id,
        tagIds: [$firstTag->id, $secondTag->id],
        locale: 'en',
    );

    expect($result->success)->toBeTrue();
    Event::assertDispatched(PostCreated::class);
    Event::assertDispatched(PostSaved::class, fn (PostSaved $event): bool => $event->categoryId === $category->id
        && $event->tagIds === [$firstTag->id, $secondTag->id]
        && $event->locale === 'en');
});

it('prevents stale post updates through lock version', function (): void {
    Event::fake([PostUpdated::class]);

    $post = Post::query()->create([
        'title' => ['en' => 'Original'],
        'context' => 'news',
        'status' => 'draft',
    ]);

    $result = app(UpdatePostAction::class)->handle($post, [
        'title' => ['en' => 'Updated'],
        'context' => 'news',
        'status' => 'draft',
        'lock_version' => 0,
    ]);

    expect($result->success)->toBeTrue()
        ->and($post->refresh()->lock_version)->toBe(1);
    Event::assertDispatched(PostUpdated::class);

    Event::fake([PostUpdated::class]);

    $stale = app(UpdatePostAction::class)->handle($post->refresh(), [
        'title' => ['en' => 'Stale'],
        'context' => 'news',
        'status' => 'draft',
        'lock_version' => 0,
    ]);

    expect($stale->success)->toBeFalse()
        ->and($stale->code)->toBe('conflict.stale_model')
        ->and($post->refresh()->title)->toBe(['en' => 'Updated']);
    Event::assertNotDispatched(PostUpdated::class);
});

class BlogTestSlugger
{
    public function generate(string $source): string
    {
        return 'configured-'.str($source)->slug()->toString();
    }
}
