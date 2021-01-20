<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondaryIdToPostAndCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->integer('secondary_id')->nullable(); // if shared, then always secondary_id

            // also, if it is a crosspost, then 9999999 is the user_id to be encrypted
            // to satisfy database integrity constraints
            // 9999999 is named thu_special, cannot be logged in, and has a user_token uniquely
            // starting with 9.
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->integer('secondary_id')->nullable(); // if shared, then always secondary_id

            // also, if it is a crosspost, then 9999999 is the user_id to be encrypted
            // to satisfy database integrity constraints
            // 9999999 is named thu_special, cannot be logged in, and has a user_token uniquely
            // starting with 9.
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
            $table->dropColumn('secondary_id');
        });
        
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('secondary_id');
        });
    }
}
