<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'craftsman_id',
        'name',
        'price',
        'category',
        'description',
        'image',
        'wa',
    ];

    public function craftsman(): BelongsTo
    {
        return $this->belongsTo(Craftsman::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
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
