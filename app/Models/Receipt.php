<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'customer_name',
        'customer_phone'
    ];
    
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
