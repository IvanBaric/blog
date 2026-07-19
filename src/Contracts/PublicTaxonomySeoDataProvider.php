<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use IvanBaric\Pages\Models\Page;

interface PublicTaxonomySeoDataProvider
{
    /**
     * @param  Collection<int, Page>  $publicPages
     * @return array<string, mixed>|null
     */
    public function taxonomy(
        Model $organization,
        Page $page,
        Collection $publicPages,
        string $kind,
        string $name,
        string $slug,
        int $postCount,
    ): ?array;
}
