<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kalnoy\Nestedset\NestedSet;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->connection();

        if (! Schema::connection($connection)->hasTable('fn_flow_tracks')) {
            Schema::connection($connection)->create('fn_flow_tracks', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('fn_flow_tracks');
    }

    /**
     * Resolve the connection the tracking table lives on.
     */
    private function connection(): string
    {
        $connection = config('nestedflowtracker.db_connection');

        return $connection === 'default'
            ? (string) config('database.default')
            : (string) $connection;
    }
};
