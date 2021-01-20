<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Support\SelectFields;
use Rebing\GraphQL\Support\Facades\GraphQL;

use Illuminate\Support\Facades\Auth;

use App\Post;

class PostsQuery extends Query
{
    protected $attributes = [
        'name' => 'posts'
    ];

    public function type(): Type
    {
        return Type::listOf(GraphQL::type('post'));
    }

    public function args(): array
    {
        return [
            "id" => ["name" => "id", "type" => Type::int()]
        ];
    }

    public function resolve($root, $args, $context, ResolveInfo $resolveInfo, Closure $getSelectFields)
    {
        /** @var SelectFields $fields */
        $fields = $getSelectFields();
        $select = $fields->getSelect();
        $with = $fields->getRelations();

        if(isset($args['id'])) {
            return Post::find($args['id']);
        }
        else {
            //$pDatas = Post::select($select)->with($with);
            return Post::all();
        }

        return $pDatas->get();
    }
}
