<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Post;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\Crypt;

$factory->define(Post::class, function (Faker $faker) {
    $dz_id = rand(1, 100);
    return [
        'type' => 'text',
        'content' => $faker->paragraphs($nb = 3, $asText = true),
        'user_id_enc' => Crypt::encryptString(strval($dz_id)),
        'ip' => $faker->ipv4,
        'user_map_enc' => Crypt::encryptString(serialize([$dz_id => "洞主"])),
        'extra' => ''
    ];
});

$factory->afterCreating(Post::class, function (Post $post, Faker $faker) {
    $cCount = rand(1, 25);
    if(rand(1, 1000) > 990) {
        $cCount = 450;
    }

    $post->comments()->saveMany(factory(App\Comment::class, $cCount)->make());
});