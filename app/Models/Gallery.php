<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'url',
    ];

    public function getUrlAttribute($value)
    {
        if (preg_match('/^https?:\/\//', $value)) {
            return $value;
        }
        if (preg_match('#^/storage/#', $value)) {
            return $value;
        }
        if (empty($value)) {
            return '/storage/gallery/placeholder.jpg';
        }
        return '/storage/' . $value;
    }
}
