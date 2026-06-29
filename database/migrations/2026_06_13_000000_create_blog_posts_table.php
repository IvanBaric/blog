<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('blog.tables.posts', 'blog_posts'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('slug');
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->string('context')->index();
            $table->string('status')->index();
            $table->string('featured_image')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->json('meta')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['team_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('blog.tables.posts', 'blog_posts'));
    }
};
