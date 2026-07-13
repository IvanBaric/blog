<?php

use IvanBaric\Blog\Models\Post;

return [
    'tables' => [
        'posts' => 'blog_posts',
    ],

    'models' => [
        'post' => Post::class,
        'user' => null,
    ],

    'contexts' => [
        'blog' => ['label' => 'Blog'],
        'news' => ['label' => 'Novosti'],
        'event' => ['label' => 'Događaji'],
        'project' => ['label' => 'Projekti'],
        'fair' => ['label' => 'Sajmovi'],
        'competition' => ['label' => 'Natjecanja'],
        'award' => ['label' => 'Nagrade'],
        'announcement' => ['label' => 'Najave'],
    ],

    'default_context' => 'blog',

    'statuses' => [
        'draft' => ['label' => 'Skica'],
        'published' => ['label' => 'Objavljeno'],
        'archived' => ['label' => 'Arhivirano'],
    ],

    'default_status' => 'draft',

    'routes' => [
        'admin_name_prefix' => 'admin.blog.',
        'admin_prefix' => 'app/blog',
        'middleware' => ['web', 'auth'],
    ],

    'public_organization' => [
        'organization_model' => class_exists('App\\Models\\Organization') ? 'App\\Models\\Organization' : null,
        'organization_slug_column' => 'slug',
        'organization_team_column' => 'team_id',
        'organization_active_scope' => 'active',
        'page_model' => class_exists('IvanBaric\\Pages\\Models\\Page') ? 'IvanBaric\\Pages\\Models\\Page' : null,
        'page_key' => 'posts',
        'post_page_slugs' => ['posts', 'objave'],
        'content_route_name' => 'public.organization.content',
        'page_route_name' => 'public.organization.page',
        'taxonomy_route_name' => 'public.organization.posts.taxonomy',
        'post_taxonomy_view' => 'blog::public.organization-content.post-taxonomy',
        'post_single_view' => 'blog::public.organization-content.post',
    ],

    'pagination' => [
        'public' => 12,
    ],

    'translatable' => [
        'default_locale' => null,
    ],

    'seo' => [
        'canonical_route_name' => 'posts.show',
    ],

    'media' => [
        'enabled' => true,
    ],

    'admin_ui' => [
        'layout' => 'layouts.app',
    ],

    'permissions' => [
        [
            'name' => 'blog',
            'slug' => 'blog',
            'label' => 'blog::permissions.group',
            'description' => 'blog::permissions.description',
            'icon' => 'newspaper',
            'sort_order' => 30,
            'items' => [
                ['name' => 'View', 'slug' => 'view', 'code' => 'blog.view', 'label' => 'blog::permissions.view', 'sort_order' => 10],
                ['name' => 'Create', 'slug' => 'create', 'code' => 'blog.create', 'label' => 'blog::permissions.create', 'sort_order' => 20],
                ['name' => 'Update', 'slug' => 'update', 'code' => 'blog.update', 'label' => 'blog::permissions.update', 'sort_order' => 30],
                ['name' => 'Delete', 'slug' => 'delete', 'code' => 'blog.delete', 'label' => 'blog::permissions.delete', 'sort_order' => 40],
                ['name' => 'Publish', 'slug' => 'publish', 'code' => 'blog.publish', 'label' => 'blog::permissions.publish', 'sort_order' => 50],
            ],
        ],
    ],

    'features' => [
        'admin_routes' => true,
        'featured_posts' => true,
    ],
];
