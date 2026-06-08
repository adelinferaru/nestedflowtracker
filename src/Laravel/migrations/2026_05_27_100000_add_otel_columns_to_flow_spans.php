<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        if ($schema->hasTable('flow_spans') && ! $schema->hasColumn('flow_spans', 'span_id')) {
            $schema->table('flow_spans', function (Blueprint $table) {
                // 16-hex span id (W3C / OpenTelemetry) and the precise start instant
                // (unix seconds with microseconds) needed for OTLP export.
                $table->string('span_id', 16)->nullable()->after('trace_id')->index();
                $table->decimal('started_at', 20, 6)->nullable()->after('duration');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection())->table('flow_spans', function (Blueprint $table) {
            $table->dropColumn(['span_id', 'started_at']);
        });
    }

    private function connection(): ?string
    {
        return config('flow.connection');
    }
};
