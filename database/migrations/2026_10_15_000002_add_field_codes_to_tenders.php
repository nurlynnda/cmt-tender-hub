<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The MOF field codes (kod bidang) a tender asks for, checked against the company's own codes when it is registered. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->json('field_codes')->nullable()->after('ministry'));
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->dropColumn('field_codes'));
    }
};
