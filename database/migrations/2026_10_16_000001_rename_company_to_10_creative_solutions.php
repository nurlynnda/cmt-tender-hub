<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The company is 10 Creative Solutions Sdn Bhd — "CMT" is only the department using this app.
 * Only the untouched placeholder name is replaced; a name an Admin already typed in Finance Settings stays.
 * Quotations already created keep the letterhead they were made with.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_profile')->where('name', 'CMT Sdn. Bhd.')->update(['name' => '10 Creative Solutions Sdn Bhd']);
    }

    public function down(): void
    {
        DB::table('company_profile')->where('name', '10 Creative Solutions Sdn Bhd')->update(['name' => 'CMT Sdn. Bhd.']);
    }
};
