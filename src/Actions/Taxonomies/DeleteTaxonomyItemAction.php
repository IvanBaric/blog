<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class DeleteTaxonomyItemAction
{
    public function execute(TaxonomyItem $item, string $type): ActionResult
    {
        $item->delete();

        return ActionResult::success(
            $type === 'category' ? __('Kategorija je obrisana.') : __('Oznaka je obrisana.'),
        );
    }
}
