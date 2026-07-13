<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $table = BlogConfigResolver::postsTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'featured_image')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('featured_image');
        });
    }

    public function down(): void
    {
        $table = BlogConfigResolver::postsTable();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'featured_image')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('featured_image')->nullable();
        });
    }
};
