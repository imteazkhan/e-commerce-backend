<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'compare_price',
        'stock',
        'image',
        'images',
        'is_featured',
    ];

    // At or below this many units a product shows up as "low stock".
    public const LOW_STOCK = 5;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_price' => 'float',
            'stock' => 'integer',
            'images' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    // Products the store shows: uncategorized, or in a category that is not paused.
    public function scopeVisible($query)
    {
        return $query->where(fn ($q) => $q->whereNull('category_id')->orWhereHas('category', fn ($c) => $c->active()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
