<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Productos extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'sku',
        'image_product',
        'activo',
        'categoria_id'
    ];
    public function categoria()
    {
        return $this->belongsTo(Categorias::class);
    }
    public function extras()
    {
        return $this->hasMany(ExtrasProduct::class, 'product_id');
    }
}
