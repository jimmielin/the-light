<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->integer("post_id")->nullable();
            $table->integer("comment_id")->nullable();
            $table->integer("user_id");

            $table->text("content");

            $table->tinyInteger("status")->default(0); // 0 = submitted, 1 = processed, 2 = escalated
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('flags');
    }
}
