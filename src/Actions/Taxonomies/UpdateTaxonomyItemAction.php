<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use Illuminate\Support\Str;
use IvanBaric\Blog\Actions\Concerns\AuthorizesBlogActions;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class UpdateTaxonomyItemAction
{
    use AuthorizesBlogActions;

    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function execute(TaxonomyItem $item, array $data, string $type): ActionResult
    {
        if ($result = $this->authorizeBlogAction('blog.update', corexis_tenant_id())) {
            return $result;
        }

        if (! $this->itemBelongsToCurrentTeam($item)) {
            return ActionResult::error(
                __('Tražena taksonomija nije dostupna.'),
                code: 'blog_taxonomy_tenant_mismatch',
                errors: ['authorization' => [__('Tražena taksonomija nije dostupna.')]],
            );
        }

        $nameChanged = $item->getAttribute('name') !== $data['name'];

        $item->forceFill([
            'name' => $data['name'],
            'slug' => $nameChanged ? Str::slug($data['name']) : $item->getAttribute('slug'),
            'description' => $data['description'],
        ])->save();

        return ActionResult::success(
            $type === 'category' ? __('Kategorija je ažurirana.') : __('Oznaka je ažurirana.'),
            $item->refresh(),
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
