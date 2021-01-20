<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommentsIndicesToCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->unique(["post_id", "sequence"], "post_id_sequence_unique");
            $table->index(["post_id", "created_at"], "post_id_created_at_idx");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropUnique("post_id_sequence_unique");
            $table->dropIndex("post_id_created_at_idx");
        });
    }
}
