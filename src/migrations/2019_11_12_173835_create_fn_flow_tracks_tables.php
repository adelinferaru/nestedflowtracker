<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Kalnoy\Nestedset\NestedSet;

class CreateFnFlowTracksTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get the correct DB Connection
        $db_connection = config('nestedflowtracker.db_connection');
        if($db_connection == "default") {
            $db_connection = \Config::get('database.default');
        }
        if(! Schema::connection($db_connection)->hasTable('fn_flow_tracks')) {
            Schema::connection($db_connection)->create('fn_flow_tracks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tracker_id', 64)->index();
                $table->bigInteger('user_id')->nullable();
                $table->text('component');
                $table->text('message')->nullable();
                $table->decimal('duration', 10, 6)->nullable();
                $table->mediumText('context')->nullable();
                $table->text('result')->nullable();

                $table->timestamps();
                NestedSet::columns($table);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Get the correct DB Connection
        $db_connection = config('nestedflowtracker.db_connection');
        if($db_connection == "default") {
            $db_connection = \Config::get('database.default');
        }
        Schema::connection($db_connection)->dropIfExists('fn_flow_tracks');
    }
}
