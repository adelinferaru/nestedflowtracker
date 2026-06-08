<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        if (! $schema->hasTable('flow_spans')) {
            $schema->create('flow_spans', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('trace_id', 32)->index();
                $table->string('name');
                $table->string('component')->index();
                $table->string('user_id')->nullable()->index();
                $table->string('status', 16)->default('ok')->index();
                $table->text('message')->nullable();
                $table->decimal('duration', 12, 6)->nullable();
                $table->json('context')->nullable();
                $table->json('result')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists('flow_spans');
    }

    /**
     * Resolve the connection the flow_spans table lives on (null = default).
     */
    private function connection(): ?string
    {
        return config('flow.connection');
    }
};
