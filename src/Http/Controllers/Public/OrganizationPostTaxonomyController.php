<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

final class OrganizationPostTaxonomyController
{
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

        return view((string) config('blog.public_organization.post_taxonomy_view', 'blog::public.organization-content.post-taxonomy'), [
            'organization' => $organization,
            'page' => $page,
            'publicPages' => $this->publicPages($organization),
            'taxonomyKind' => $taxonomyKind,
            'taxonomyItem' => $taxonomyItem,
            'posts' => $posts,
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

    private function page(Model $organization, string $pageSlug): Model
    {
        $model = $this->pageModel();
        $teamId = (int) $organization->getAttribute($this->organizationTeamColumn());
        $normalized = Str::slug($pageSlug);
        $aliases = (array) config('pages.public_slug_aliases', []);
        $canonical = (string) ($aliases[$normalized] ?? $normalized);
        $slugCandidates = array_values(array_unique([$normalized, $canonical]));

        return $model::query()
            ->where((string) config('corexis.tenancy.id_column', 'team_id'), $teamId)
            ->published()
            ->where(function (Builder $query) use ($slugCandidates): void {
                $query->whereIn('slug', $slugCandidates);

                if (Schema::hasColumn(BlogConfigResolver::pagesTable(), 'page_key')) {
                    $query->orWhereIn('page_key', $slugCandidates);
                }
            })
            ->firstOrFail();
    }

    /**
     * @param  array<int, string>  $types
     */
    private function taxonomyItem(int $teamId, array $types, string $taxonomySlug): object
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $taxonomyModel = TaxonomyModels::taxonomy();
        $taxonomyItemsTable = (new $taxonomyItemModel)->getTable();
        $taxonomiesTable = (new $taxonomyModel)->getTable();
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

    private function publicPages(Model $organization)
    {
        $model = $this->pageModel();

        return $model::query()
            ->where(
                (string) config('corexis.tenancy.id_column', 'team_id'),
                (int) $organization->getAttribute($this->organizationTeamColumn()),
            )
            ->published()
            ->ordered()
            ->get();
    }

    private function pageModel(): string
    {
        $model = BlogConfigResolver::publicOrganizationPageModel();
        abort_unless($model !== null, 404);

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
}
