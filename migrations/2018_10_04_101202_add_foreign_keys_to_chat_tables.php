<?php

use Cmgmyr\Messenger\Models\Models;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToChatTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(Models::table('messages'), function (Blueprint $table) {
            $table->foreign('thread_id')->references('id')->on(Models::table('threads'))->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table(Models::table('participants'), function (Blueprint $table) {
            $table->foreign('thread_id')->references('id')->on(Models::table('threads'))->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(Models::table('messages'), function (Blueprint $table) {
            $table->dropForeign('thread_id');
        });

        Schema::table(Models::table('participants'), function (Blueprint $table) {
            $table->dropForeign('thread_id');
        });
    }
}
