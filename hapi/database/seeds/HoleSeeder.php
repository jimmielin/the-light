<?php

use Illuminate\Database\Seeder;

class HoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\User::class, 100)->create();

        // now create the holes...
        factory(App\Post::class, 10000)->create();
    }
}
