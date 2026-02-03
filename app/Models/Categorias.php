<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorias extends Model
{
    //
    protected $fillable = ['local_id', 'nombre', 'orden', 'activo', 'image_category'];

    public function user()
    {
        return $this->belongsTo(Local::class);
    }
    public function productos()
    {
        return $this->hasMany(Productos::class, 'categoria_id');
    }

    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
