<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVerdictToPostsAndCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('verdict')->nullable()->after('hidden');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->string('verdict')->nullable()->after('hidden');
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
            $table->dropColumn('verdict');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('verdict');
        });
    }
}
