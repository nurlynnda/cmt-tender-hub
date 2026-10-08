<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Costing frequency becomes a whole number (monthly × N → N); Year and Group go; a line can carry a typed selling price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_lines', function (Blueprint $table) {
            $table->unsignedInteger('frequency_count')->default(1)->after('quantity');
            $table->unsignedBigInteger('unit_price_override_sen')->nullable()->after('margin_bp');
        });
        DB::table('costing_lines')->update(['frequency_count' => DB::raw("CASE WHEN frequency = 'monthly' THEN GREATEST(months, 1) ELSE 1 END")]);
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn(['frequency', 'months', 'project_year', 'pd_group']));
        Schema::table('costing_lines', fn (Blueprint $table) => $table->renameColumn('frequency_count', 'frequency'));
    }

    public function down(): void
    {
        Schema::table('costing_lines', fn (Blueprint $table) => $table->renameColumn('frequency', 'frequency_count'));
        Schema::table('costing_lines', function (Blueprint $table) {
            $table->string('frequency', 10)->default('one_off')->after('quantity');
            $table->unsignedSmallInteger('months')->default(1)->after('frequency');
            $table->unsignedTinyInteger('project_year')->default(1)->after('months');
            $table->string('pd_group', 20)->default('principal')->after('position');
        });
        DB::table('costing_lines')->update([
            'frequency' => DB::raw("CASE WHEN frequency_count > 1 THEN 'monthly' ELSE 'one_off' END"),
            'months' => DB::raw('LEAST(frequency_count, 600)'),
        ]);
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn(['frequency_count', 'unit_price_override_sen']));
    }
};
