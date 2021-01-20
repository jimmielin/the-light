<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId("user_id");
            $table->string("code");
            $table->integer("remaining")->default(0);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string("invited_code")->nullable(); // record invite code
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn("invited_code");
        });
    }
}
