<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->connection());

        if ($schema->hasTable('flow_spans')) {
            $schema->table('flow_spans', function (Blueprint $table) {
                // Speeds up the viewer's "recent flows" listing and flow:prune,
                // both of which filter/order by created_at.
                $table->index('created_at', 'flow_spans_created_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection())->table('flow_spans', function (Blueprint $table) {
            $table->dropIndex('flow_spans_created_at_index');
        });
    }

    private function connection(): ?string
    {
        return config('flow.connection');
    }
};
