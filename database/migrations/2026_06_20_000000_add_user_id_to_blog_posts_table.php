<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('blog.tables.posts', 'blog_posts'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('blog.tables.posts', 'blog_posts'), 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('team_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table(config('blog.tables.posts', 'blog_posts'), function (Blueprint $table): void {
            if (Schema::hasColumn(config('blog.tables.posts', 'blog_posts'), 'user_id')) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
