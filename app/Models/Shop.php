<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
