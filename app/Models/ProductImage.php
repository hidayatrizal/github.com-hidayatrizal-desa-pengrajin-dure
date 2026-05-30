<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageAttribute($value)
    {
        if (preg_match('/^https?:\/\//', $value)) {
            return $value;
        }
        if (preg_match('#^/storage/#', $value)) {
            return $value;
        }
        if (empty($value)) {
            return '/storage/products/placeholder.jpg';
        }
        return '/storage/' . $value;
    }
}
