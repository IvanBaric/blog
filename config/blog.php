<?php

use IvanBaric\Blog\Models\Post;

return [
    'tables' => [
        'posts' => 'blog_posts',
    ],

    'models' => [
        'post' => Post::class,
    ],

    'team_resolver' => class_exists('App\\Resolvers\\TeamResolver') ? 'App\\Resolvers\\TeamResolver' : null,

    'contexts' => [
        'blog' => ['label' => 'Blog'],
        'news' => ['label' => 'News'],
        'event' => ['label' => 'Events'],
        'project' => ['label' => 'Projects'],
        'fair' => ['label' => 'Fairs'],
        'competition' => ['label' => 'Competitions'],
        'award' => ['label' => 'Awards'],
        'announcement' => ['label' => 'Announcements'],
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
        'admin_prefix' => 'admin/blog',
        'middleware' => ['web', 'auth'],
    ],

    'pagination' => [
        'admin' => 15,
        'public' => 12,
    ],

    'translatable' => [
        'enabled' => true,
        'fields' => ['title', 'excerpt', 'content'],
        'default_locale' => null,
    ],

    'slug' => [
        'source' => 'title',
        'column' => 'slug',
        'scoped_to_team' => true,
        'sanigen' => [
            'generator' => null,
            'method' => 'generate',
        ],
    ],

    'taxonomy' => [
        'enabled' => true,
        'trait' => 'IvanBaric\\Taxonomy\\Traits\\HasTaxonomies',
        'filter_parameter' => 'taxonomy',
    ],

    'seo' => [
        'enabled' => true,
        'trait' => 'IvanBaric\\Seo\\Concerns\\HasSeo',
    ],

    'media' => [
        'enabled' => true,
        'gallery_trait' => 'IvanBaric\\Gallery\\Concerns\\HasGallery',
        'featured_image_column' => 'featured_image',
    ],

    'admin_ui' => [
        'enabled' => true,
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
        'soft_deletes' => true,
        'featured_posts' => true,
        'scheduled_posts' => true,
        'events' => true,
        'location' => true,
        'sort_order' => true,
        'taxonomy_filters' => true,
        'seo' => true,
        'media' => true,
    ],
];
