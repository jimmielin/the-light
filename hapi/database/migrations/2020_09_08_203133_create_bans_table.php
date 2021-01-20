<?php

use Carbon\Carbon;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('user_id')->constrained();
            $table->foreignId('post_id')->nullable();
            $table->foreignId('comment_id')->nullable(); // one of these present

            $table->timestamp('until')->default(Carbon::now());
            $table->text('reason'); // usually autofilled, but may also be manually input
        });

        Schema::table('flags', function (Blueprint $table) {
            $table->foreignId('post_id')->change();
            $table->foreignId('comment_id')->change();

            $table->foreignId('ban_id')->nullable(); // attach flags to ban
            // so valid flags would have a ban attached (otherwise null if they are not acted upon)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bans');

        Schema::table('flags', function (Blueprint $table) {
            $table->dropColumn('ban_id');
        });
    }
}
