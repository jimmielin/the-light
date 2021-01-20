<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNicknameFieldsToPostsAndComments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('user_map_enc')->nullable();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->string('user_nickname')->default("Unknown");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('user_map_enc');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('user_nickname');
        });
    }
}
