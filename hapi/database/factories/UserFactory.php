<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\User;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$uniquePfx = Str::random(30);
$autoIncrement = autoIncrement();

$factory->define(User::class, function (Faker $faker) use ($uniquePfx, $autoIncrement) {
    $autoIncrement->next();
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'email_verified_at' => now(),
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'remember_token' => Str::random(10),

        'ring' => 4,
        // 'user_token' => "9" . Str::random(33)
        'user_token' => "9" . $uniquePfx . sprintf("%03d", $autoIncrement->current()), // up to 999 can be generated in one factory at a time
    ];
});

function autoIncrement() {
    for($i = 0; $i < 1000; $i++) {
        yield $i;
    }
}