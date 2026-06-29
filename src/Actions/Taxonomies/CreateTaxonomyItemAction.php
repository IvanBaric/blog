<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class CreateTaxonomyItemAction
{
    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function execute(Taxonomy $taxonomy, array $data, string $type): ActionResult
    {
        $item = TaxonomyItem::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'name' => $data['name'],
            'description' => $data['description'],
        ]);

        return ActionResult::success(
            $type === 'category' ? __('Kategorija je dodana.') : __('Oznaka je dodana.'),
            $item,
        );
    }
}
