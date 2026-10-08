<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostingSubItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'quantity' => 'integer', 'unit_cost_sen' => 'integer'];
    }
}
