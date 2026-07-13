<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = BlogConfigResolver::postsTable();

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'is_featured')) {
            return;
        }

        DB::table($tableName)
            ->where('status', 'archived')
            ->where('is_featured', true)
            ->update(['is_featured' => false]);
    }

    public function down(): void
    {
        // The previous featured state cannot be reconstructed reliably.
    }
};
