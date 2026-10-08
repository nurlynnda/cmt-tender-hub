<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The one company letterhead (Admin, Finance Settings). Exactly one row, inserted by the migration. */
class CompanyProfile extends Model
{
    protected $table = 'company_profile';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['default_sst_bp' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrFail();
    }

    /** The copy stored on each new quotation. */
    public function letterhead(): array
    {
        return $this->only(['name', 'registration_no', 'sst_no', 'address', 'phone', 'email', 'website', 'stamp_path']);
    }
}
