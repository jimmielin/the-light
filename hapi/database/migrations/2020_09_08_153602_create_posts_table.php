<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string("type", 8)->default("text"); // text, image, ...
            $table->text("content");
            $table->text("extra");

            $table->string("user_id_enc", 254);
            $table->string("ip", 46)->default("0.0.0.1");

            $table->tinyInteger("hidden")->default(0); // 0 = visible, 1 = folded by user, 2 = admin visible only

            $table->string("tag")->nullable();
            $table->integer("flag_count")->default(0);
            $table->integer("reply_count")->default(0);
            $table->integer("favorite_count")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
