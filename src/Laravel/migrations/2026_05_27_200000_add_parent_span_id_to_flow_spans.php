<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        if ($schema->hasTable('flow_spans') && ! $schema->hasColumn('flow_spans', 'parent_span_id')) {
            $schema->table('flow_spans', function (Blueprint $table) {
                // 16-hex id of the enclosing span (W3C/OTLP parentSpanId). Lets parent
                // linkage work without the database row id, so non-database drivers
                // (log/otel) can build the tree from in-memory spans.
                $table->string('parent_span_id', 16)->nullable()->after('span_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection())->table('flow_spans', function (Blueprint $table) {
            $table->dropColumn('parent_span_id');
        });
    }

    private function connection(): ?string
    {
        return config('flow.connection');
    }
};
