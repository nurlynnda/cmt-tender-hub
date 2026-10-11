<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A tender can be registered before anyone is assigned; until a PIC is set, anyone may edit it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->unsignedBigInteger('pic_id')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->unsignedBigInteger('pic_id')->nullable(false)->change());
    }
};
