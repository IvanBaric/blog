<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Actions\Concerns;

use IvanBaric\Blog\Models\Post;
use IvanBaric\Corexis\Contracts\TenantResolver;
use IvanBaric\Corexis\Data\ActionResult;

trait AuthorizesBlogActions
{
    protected function authorizeBlogAction(string $ability, mixed $arguments = []): ?ActionResult
    {
        $tenantResolver = app(TenantResolver::class);

        if (
            $arguments instanceof Post
            && $tenantResolver->enabled()
            && (
                $tenantResolver->id() === null
                || (string) $arguments->getAttribute(config('corexis.tenancy.id_column', 'team_id')) !== (string) $tenantResolver->id()
            )
        ) {
            return ActionResult::error(
                __('Tražena objava nije dostupna.'),
                code: 'blog_post_tenant_mismatch',
                errors: ['authorization' => [__('Tražena objava nije dostupna.')]],
            );
        }

        $result = corexis_authorization_result($ability, $arguments);

        if ($result) {
            return $result;
        }

        return null;
    }
}
