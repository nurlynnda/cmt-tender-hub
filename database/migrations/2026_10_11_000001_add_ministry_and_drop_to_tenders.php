<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('ministry')->nullable()->after('client');
            $table->timestamp('dropped_at')->nullable()->after('lost_at');
            $table->text('drop_reason')->nullable()->after('dropped_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->dropColumn(['ministry', 'dropped_at', 'drop_reason']));
    }
};
