<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;

use App\Post;

class PostType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Post',
        'description' => 'Hole Posts',
        'model' => Post::class
    ];

    public function fields(): array
    {
        return [
            "id" => [
                "type" => Type::nonNull(Type::int()),
                "description" => "PID"
            ],
            "content" => [
                "type" => Type::nonNull(Type::string()),
                "description" => "content"
            ],
            "type" => [
                "type" => Type::nonNull(Type::string()),
                "description" => "text|image"
            ],
            "tag" => [
                "type" => Type::string(),
                "description" => "Tag"
            ],
            "created_at" => [
                "type" => Type::int(),
                "description" => "Posting date",
                "resolve" => function($root, $args) { return $root->created_at->timestamp; }
            ],
            "reply_count" => ["type" => Type::int(), "description" => "# of comments"],
            "favorite_count" => ["type" => Type::int(), "description" => "# of favorites"],

            // Virtual fields
            "url" => [
                "selectable" => false,
                "type" => Type::string(),
                "description" => "image type? url",
                "resolve" => function($root, $args) {
                    return ($root->type == "image" ? Storage::disk("s3")->url($root->extra) : null);
                },
            ],
            "attention" => [
                "selectable" => false,
                "type" => Type::boolean(),
                "description" => "Is user following post",
                "resolve" => function($root, $args) {
                    // tbd
                    return false;
                }
            ]
        ];
    }
}
