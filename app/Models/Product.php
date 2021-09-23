<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'quantity',
        'description'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function shops()
    {
        return $this->belongsToMany(Shop::class)
                    ->withPivot('quantity');
    }
}
