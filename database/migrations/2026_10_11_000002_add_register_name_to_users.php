<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The PIC name used in the tender register, so re-imports find the person after an admin renames them. */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('register_name')->nullable()->index()->after('name'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('register_name'));
    }
};
