<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class DeleteTaxonomyItemAction
{
    use AuthorizesBlogActions;

    public function execute(TaxonomyItem $item, string $type): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.delete', corexis_tenant_id())) {
            return $result;
        }

        if (! $this->itemBelongsToCurrentTeam($item)) {
            return ActionResult::error(
                __('Tražena taksonomija nije dostupna.'),
                code: 'blog_taxonomy_tenant_mismatch',
                errors: ['authorization' => [__('Tražena taksonomija nije dostupna.')]],
            );
        }

        $item->delete();

        return ActionResult::success(
            $type === 'category' ? __('Kategorija je obrisana.') : __('Oznaka je obrisana.'),
        );
    }

    private function itemBelongsToCurrentTeam(TaxonomyItem $item): bool
    {
        if (! config('corexis.tenancy.enabled', false)) {
            return true;
        }

        $tenantId = corexis_tenant_id();

        return $tenantId !== null
            && (string) $item->getAttribute((string) config('corexis.tenancy.id_column', 'team_id')) === (string) $tenantId;
    }
}
