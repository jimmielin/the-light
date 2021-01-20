<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Comment;
use Faker\Generator as Faker;

$factory->define(Comment::class, function (Faker $faker) {
    $poster_id = rand(1, 100);
    return [
        'type' => 'text',
        'content' => $faker->paragraphs($nb = 2, $asText = true),
        'user_id' => $poster_id,
        'ip' => $faker->ipv4,
        'extra' => ''
    ];
});
