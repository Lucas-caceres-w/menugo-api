<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoItems extends Model
{
    protected $fillable = [
        'producto_nombre',
        'precio_unitario',
        'cantidad',
        'subtotal',
        'producto_id',
        'pedido_id'
    ];
    public function pedido()
    {
        return $this->belongsTo(Pedidos::class);
    }
}
