<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $table = BlogConfigResolver::postsTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_featured') || ! Schema::hasColumn($table, 'status')) {
            return;
        }

        DB::table($table)
            ->where('status', '!=', 'published')
            ->where('is_featured', true)
            ->update(['is_featured' => false]);
    }

    public function down(): void
    {
        // The previous featured state cannot be reconstructed safely.
    }
};
