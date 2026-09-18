<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function getImageUrlAttribute(): string
    {
        return route('signatures.image', $this);
    }
}
