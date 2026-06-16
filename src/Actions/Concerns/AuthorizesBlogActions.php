<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Concerns;

use IvanBaric\Blog\Data\ActionResult;

trait AuthorizesBlogActions
{
    protected function authorizeBlogAction(string $ability, mixed $arguments = []): ?ActionResult
    {
        $result = corexis_authorization_result($ability, $arguments);

        return $result ? ActionResult::fromCorexis($result) : null;
    }
}
