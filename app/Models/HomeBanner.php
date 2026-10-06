<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    public const SECTIONS = ['hero', 'tile', 'lifestyle', 'editorial'];

    protected $fillable = ['section', 'image', 'eyebrow', 'title', 'text', 'cta', 'link', 'alt', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
