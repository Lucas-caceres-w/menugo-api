<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoItemExtra extends Model
{
    protected $fillable = [
        'pedido_item_id',
        'extra_id',
        'extra_nombre',
        'extra_precio',
        'cantidad'
    ];
    public function item()
    {
        return $this->belongsTo(PedidoItems::class, 'pedido_item_id');
    }
}
