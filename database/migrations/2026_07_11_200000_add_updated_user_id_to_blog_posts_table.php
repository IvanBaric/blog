<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = BlogConfigResolver::postsTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'updated_user_id')) {
                $table->foreignId('updated_user_id')->nullable()->after('user_id')->index();
            }
        });

        if (Schema::hasColumn($tableName, 'user_id') && Schema::hasColumn($tableName, 'updated_user_id')) {
            DB::table($tableName)
                ->whereNull('updated_user_id')
                ->whereNotNull('user_id')
                ->update(['updated_user_id' => DB::raw('user_id')]);
        }
    }

    public function down(): void
    {
        $tableName = BlogConfigResolver::postsTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'updated_user_id')) {
                $table->dropIndex(['updated_user_id']);
                $table->dropColumn('updated_user_id');
            }
        });
    }
};
