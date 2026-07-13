<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

final class CreateTaxonomyItemAction
{
    use AuthorizesBlogActions;

    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function execute(Taxonomy $taxonomy, array $data, string $type): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.create', corexis_tenant_id())) {
            return $result;
        }

        if (! $this->taxonomyBelongsToCurrentTeam($taxonomy)) {
            return $this->tenantMismatchResult();
        }

        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $item = $taxonomyItemModel::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'name' => $data['name'],
            'description' => $data['description'],
        ]);

        return ActionResult::success(
            $type === 'category' ? __('Kategorija je dodana.') : __('Oznaka je dodana.'),
            $item,
        );
    }

    private function taxonomyBelongsToCurrentTeam(Taxonomy $taxonomy): bool
    {
        if (! config('corexis.tenancy.enabled', false)) {
            return true;
        }

        $tenantId = corexis_tenant_id();

        return $tenantId !== null
            && (string) $taxonomy->getAttribute((string) config('corexis.tenancy.id_column', 'team_id')) === (string) $tenantId;
    }

    private function tenantMismatchResult(): ActionResult
    {
        return ActionResult::error(
            __('Tražena taksonomija nije dostupna.'),
            code: 'blog_taxonomy_tenant_mismatch',
            errors: ['authorization' => [__('Tražena taksonomija nije dostupna.')]],
        );
    }
}
