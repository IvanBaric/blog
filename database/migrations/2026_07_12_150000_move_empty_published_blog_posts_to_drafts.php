<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\PublishablePostContent;

return new class extends Migration
{
    public function up(): void
    {
        $table = BlogConfigResolver::postsTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('status', 'published')
            ->orderBy('id')
            ->select(['id', 'content'])
            ->chunkById(100, function ($posts) use ($table): void {
                foreach ($posts as $post) {
                    $content = is_string($post->content)
                        ? (json_decode($post->content, true) ?? $post->content)
                        : $post->content;

                    if (PublishablePostContent::isPresent($content)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $post->id)
                        ->update([
                            'status' => 'draft',
                            'published_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // A previously invalid publication state cannot be restored safely.
    }
};
