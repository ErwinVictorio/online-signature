<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['placements' => 'array'];
    }
}
