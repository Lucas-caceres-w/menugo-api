<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtrasProduct extends Model
{
    protected $table = 'producto_extras';

    protected $fillable = [
        'id',
        'product_id',
        'nombre',
        'precio'
    ];

    public function producto()
    {
        return $this->belongsTo(Productos::class, 'product_id');
    }
}
