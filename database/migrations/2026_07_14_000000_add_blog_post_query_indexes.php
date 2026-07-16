<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const INDEXES = [
        'blog_posts_tenant_status_published_idx' => ['team_id', 'status', 'published_at'],
        'blog_posts_tenant_status_featured_idx' => ['team_id', 'status', 'is_featured'],
        'blog_posts_tenant_order_idx' => ['team_id', 'sort_order', 'published_at', 'created_at'],
    ];

    public function up(): void
    {
        $tableName = BlogConfigResolver::postsTable();

        foreach (self::INDEXES as $name => $columns) {
            if (Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($columns, $name): void {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        $tableName = BlogConfigResolver::postsTable();

        foreach (array_keys(self::INDEXES) as $name) {
            if (! Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};
