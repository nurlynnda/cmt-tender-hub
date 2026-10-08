<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Company-wide defaults copied into each new project. Exactly one row (inserted by the migration). */
class FinanceSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['project_charge_bp' => 'integer', 'commission_share_bp' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrFail();
    }
}
