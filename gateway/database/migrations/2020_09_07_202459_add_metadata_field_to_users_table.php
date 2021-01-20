<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMetadataFieldToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string("user_token", 128);

            $table->string("first_seen_ip", 46)->default("0.0.0.1"); // SO#166132
            $table->string("last_seen_ip", 46)->default("0.0.0.1"); // SO#166132
            $table->integer("last_seen")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_token', 'first_seen_ip', 'last_seen_ip', 'last_seen']);
        });
    }
}
