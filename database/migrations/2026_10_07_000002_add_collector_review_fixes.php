<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A run that stops reporting progress is treated as dead (worker/PC restarted mid-run).
        Schema::table('collection_runs', function (Blueprint $table) {
            $table->timestamp('heartbeat_at')->nullable()->after('started_at');
        });

        // Lets the Closed/All lists sort 200k+ rows without a full sort.
        Schema::table('collected_tenders', function (Blueprint $table) {
            $table->index(['status', 'closing_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('collected_tenders', fn (Blueprint $table) => $table->dropIndex(['status', 'closing_date', 'id']));
        Schema::table('collection_runs', fn (Blueprint $table) => $table->dropColumn('heartbeat_at'));
    }
};
