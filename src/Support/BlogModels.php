<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Support;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Blog\Models\Post;

final class BlogModels
{
    /** @return class-string<Post> */
    public static function post(): string
    {
        return BlogConfigResolver::postModel();
    }

    /** @return class-string<Model> */
    public static function user(): string
    {
        return BlogConfigResolver::userModel();
    }
}
