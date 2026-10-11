<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A document/task can have subtasks (one level deep). Deleting a task deletes its subtasks. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_documents', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('tender_id')->constrained('tender_documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tender_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
