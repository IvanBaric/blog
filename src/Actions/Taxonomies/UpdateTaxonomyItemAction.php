<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Taxonomies;

use Illuminate\Support\Str;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class UpdateTaxonomyItemAction
{
    /**
     * @param  array{name: string, description: string|null}  $data
     */
    public function execute(TaxonomyItem $item, array $data, string $type): ActionResult
    {
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
}
