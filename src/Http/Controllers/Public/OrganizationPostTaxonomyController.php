<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IvanBaric\Blog\Contracts\PublicTaxonomySeoDataProvider;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Pages\Models\Page;
use IvanBaric\Pages\Support\PageHierarchy;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

final class OrganizationPostTaxonomyController
{
    public function __construct(private readonly PageHierarchy $hierarchy) {}

    public function __invoke(string $organizationSlug, string $pageSlug, string $taxonomyKind, string $taxonomySlug): View
    {
        abort_unless(in_array($taxonomyKind, ['kategorija', 'oznaka'], true), 404);

        $organization = $this->organization($organizationSlug);
        $page = $this->page($organization, $pageSlug);
        abort_unless((string) $page->getAttribute('page_key') === $this->pageKey(), 404);

        $teamId = (int) $organization->getAttribute($this->organizationTeamColumn());
        $types = $taxonomyKind === 'kategorija' ? ['category', 'post_category'] : ['tags'];
        $taxonomyItem = $this->taxonomyItem($teamId, $types, $taxonomySlug);
        $postModel = BlogModels::post();
        $postInstance = new $postModel;
        $postTable = $postInstance->getTable();
        $postMorphClass = $postInstance->getMorphClass();
        $taxonomyTenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');
        $scopePivotTenant = config('corexis.tenancy.enabled', false)
            && Schema::hasColumn('taxonomyables', $taxonomyTenantColumn);

        $posts = $postModel::query()
            ->select([
                $postTable.'.id',
                $postTable.'.team_id',
                $postTable.'.uuid',
                $postTable.'.slug',
                $postTable.'.title',
                $postTable.'.excerpt',
                $postTable.'.status',
                $postTable.'.published_at',
            ])
            ->with([
                'galleries' => fn ($query) => $query
                    ->forCollection(Post::FEATURED_IMAGE_COLLECTION)
                    ->with('media'),
            ])
            ->forTenant($teamId)
            ->published()
            ->whereExists(function ($query) use ($taxonomyItem, $teamId, $postTable, $postMorphClass, $scopePivotTenant, $taxonomyTenantColumn): void {
                $query->selectRaw('1')
                    ->from('taxonomyables')
                    ->whereColumn('taxonomyables.taxonomyable_id', $postTable.'.id')
                    ->where('taxonomyables.taxonomyable_type', $postMorphClass)
                    ->where('taxonomyables.taxonomy_item_id', $taxonomyItem->id)
                    ->when($scopePivotTenant, fn ($query) => $query->where('taxonomyables.'.$taxonomyTenantColumn, $teamId));
            })
            ->ordered()
            ->paginate((int) config('blog.pagination.public', 12));
        $publicPages = $this->publicPages($organization);

        return view($this->publicView(), [
            'organization' => $organization,
            'page' => $page,
            'publicPages' => $publicPages,
            'taxonomyKind' => $taxonomyKind,
            'taxonomyItem' => $taxonomyItem,
            'posts' => $posts,
            'socialMeta' => $this->socialMeta(
                $organization,
                $page,
                $publicPages,
                $taxonomyKind,
                $taxonomyItem,
                $posts->total(),
            ),
        ]);
    }

    private function organization(string $organizationSlug): Model
    {
        $model = BlogConfigResolver::publicOrganizationModel();
        abort_unless($model !== null, 404);

        $query = $model::query();
        $activeScope = (string) config('blog.public_organization.organization_active_scope', '');

        if ($activeScope !== '' && method_exists($model, 'scope'.Str::studly($activeScope))) {
            $query->{$activeScope}();
        }

        return $query
            ->where($this->organizationSlugColumn(), $organizationSlug)
            ->firstOrFail();
    }

    private function page(Model $organization, string $pageSlug): Page
    {
        $model = $this->pageModel();
        $teamId = (int) $organization->getAttribute($this->organizationTeamColumn());
        $normalizedPath = collect(explode('/', trim($pageSlug, '/')))
            ->map(fn (string $segment): string => Str::slug($segment))
            ->implode('/');

        $pages = $model::query()
            ->where((string) config('corexis.tenancy.id_column', 'team_id'), $teamId)
            ->published()
            ->navigationVisible()
            ->ordered()
            ->get();

        $page = $pages->first(fn (Page $candidate): bool => $this->hierarchy->slugPath($candidate, $pages) === $normalizedPath);

        return $page ?? abort(404);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function taxonomyItem(int $teamId, array $types, string $taxonomySlug): \stdClass
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $taxonomyModel = TaxonomyModels::taxonomy();
        $taxonomyItemInstance = new $taxonomyItemModel;
        $taxonomyInstance = new $taxonomyModel;
        abort_unless($taxonomyItemInstance instanceof Model && $taxonomyInstance instanceof Model, 500);
        $taxonomyItemsTable = $taxonomyItemInstance->getTable();
        $taxonomiesTable = $taxonomyInstance->getTable();
        $tenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');

        return DB::table($taxonomyItemsTable)
            ->join($taxonomiesTable, $taxonomyItemsTable.'.taxonomy_id', '=', $taxonomiesTable.'.id')
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query
                ->where($taxonomyItemsTable.'.'.$tenantColumn, $teamId)
                ->where($taxonomiesTable.'.'.$tenantColumn, $teamId))
            ->whereIn($taxonomiesTable.'.type', $types)
            ->where($taxonomyItemsTable.'.slug', Str::slug($taxonomySlug))
            ->first([
                $taxonomyItemsTable.'.id',
                $taxonomyItemsTable.'.name',
                $taxonomyItemsTable.'.slug',
                $taxonomiesTable.'.type',
            ]) ?? abort(404);
    }

    /** @return Collection<int, Page> */
    private function publicPages(Model $organization): Collection
    {
        $model = $this->pageModel();

        return $model::query()
            ->where(
                (string) config('corexis.tenancy.id_column', 'team_id'),
                (int) $organization->getAttribute($this->organizationTeamColumn()),
            )
            ->published()
            ->navigationVisible()
            ->ordered()
            ->get();
    }

    /** @return class-string<Page> */
    private function pageModel(): string
    {
        $model = BlogConfigResolver::publicOrganizationPageModel();
        abort_unless($model !== null, 404);

        abort_unless(is_a($model, Page::class, true), 500);

        return $model;
    }

    private function pageKey(): string
    {
        return (string) config('blog.public_organization.page_key', 'posts');
    }

    private function organizationSlugColumn(): string
    {
        return (string) config('blog.public_organization.organization_slug_column', 'slug');
    }

    private function organizationTeamColumn(): string
    {
        return (string) config('blog.public_organization.organization_team_column', 'team_id');
    }

    /**
     * @param  Collection<int, Page>  $publicPages
     * @return array<string, mixed>|null
     */
    private function socialMeta(
        Model $organization,
        Page $page,
        Collection $publicPages,
        string $kind,
        \stdClass $taxonomyItem,
        int $postCount,
    ): ?array {
        $provider = config('blog.public_organization.seo_data_provider');

        if (! is_string($provider) || $provider === '') {
            return null;
        }

        $resolved = app($provider);

        return $resolved instanceof PublicTaxonomySeoDataProvider
            ? $resolved->taxonomy(
                $organization,
                $page,
                $publicPages,
                $kind,
                (string) data_get($taxonomyItem, 'name'),
                (string) data_get($taxonomyItem, 'slug'),
                $postCount,
            )
            : null;
    }

    /** @return view-string */
    private function publicView(): string
    {
        $view = config('blog.public_organization.post_taxonomy_view', 'blog::public.organization-content.post-taxonomy');
        abort_unless(is_string($view) && view()->exists($view), 500);

        return $view;
    }
}
