<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IvanBaric\Blog\Models\Post;

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
        $postModel = config('blog.models.post', Post::class);
        $postInstance = new $postModel;
        $postTable = $postInstance->getTable();
        $postMorphClass = $postInstance->getMorphClass();

        $posts = $postModel::query()
            ->forTeam($teamId)
            ->published()
            ->whereExists(function ($query) use ($taxonomyItem, $teamId, $postTable, $postMorphClass): void {
                $query->selectRaw('1')
                    ->from('taxonomyables')
                    ->whereColumn('taxonomyables.taxonomyable_id', $postTable.'.id')
                    ->where('taxonomyables.taxonomyable_type', $postMorphClass)
                    ->where('taxonomyables.taxonomy_item_id', $taxonomyItem->id)
                    ->where('taxonomyables.team_id', $teamId);
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
        $model = config('blog.public_organization.organization_model');
        abort_unless(is_string($model) && is_subclass_of($model, Model::class), 404);

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
            ->forTeam($teamId)
            ->published()
            ->where(function (Builder $query) use ($slugCandidates): void {
                $query->whereIn('slug', $slugCandidates);

                if (Schema::hasColumn(config('pages.tables.pages', 'pages'), 'page_key')) {
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
        return DB::table('taxonomy_items')
            ->join('taxonomies', 'taxonomy_items.taxonomy_id', '=', 'taxonomies.id')
            ->where('taxonomy_items.team_id', $teamId)
            ->where('taxonomies.team_id', $teamId)
            ->whereIn('taxonomies.type', $types)
            ->where('taxonomy_items.slug', Str::slug($taxonomySlug))
            ->first([
                'taxonomy_items.id',
                'taxonomy_items.name',
                'taxonomy_items.slug',
                'taxonomies.type',
            ]) ?? abort(404);
    }

    private function publicPages(Model $organization)
    {
        $model = $this->pageModel();

        return $model::query()
            ->forTeam((int) $organization->getAttribute($this->organizationTeamColumn()))
            ->published()
            ->ordered()
            ->get();
    }

    private function pageModel(): string
    {
        $model = config('blog.public_organization.page_model');
        abort_unless(is_string($model) && is_subclass_of($model, Model::class), 404);

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
