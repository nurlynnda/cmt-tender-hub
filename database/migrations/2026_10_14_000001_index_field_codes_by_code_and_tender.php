<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Find Tenders field-code filter looks up tenders by code prefix ("2101%"). With only an index on code,
 * MySQL scanned all ~226k rows; this index answers the lookup on its own (3–7 s → about 0.1 s for open tenders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collected_tender_field_codes', fn (Blueprint $table) => $table->index(['code', 'collected_tender_id'], 'fc_code_tender'));
    }

    public function down(): void
    {
        Schema::table('collected_tender_field_codes', fn (Blueprint $table) => $table->dropIndex('fc_code_tender'));
    }
};
