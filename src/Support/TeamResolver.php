<?php

namespace IvanBaric\Blog\Support;

use IvanBaric\Corexis\Contracts\TenantResolver;

final class TeamResolver
{
    public function resolve(): ?int
    {
        if (app()->bound(TenantResolver::class)) {
            $resolver = app(TenantResolver::class);

            if ($resolver->enabled()) {
                $tenantId = $resolver->id();

                return $tenantId === null ? null : (int) $tenantId;
            }
        }

        $resolver = config('blog.team_resolver');

        if (! is_string($resolver) || ! class_exists($resolver)) {
            return null;
        }

        $instance = app($resolver);

        foreach (['resolve', 'currentTeamId', 'teamId', 'id'] as $method) {
            if (method_exists($instance, $method)) {
                $value = $instance->{$method}();

                return is_object($value) && isset($value->id) ? (int) $value->id : ($value === null ? null : (int) $value);
            }
        }

        return null;
    }
}
