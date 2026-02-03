<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transacciones extends Model
{
    protected $table = 'transacciones';

    protected $fillable = [
        'pedido_id',
        'total',
        'medio_pago',
        'estado',
        'referencia_externa',
        'fecha_pago',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'total'      => 'decimal:2',
    ];
    public function pedido()
    {
        return $this->belongsTo(Pedidos::class);
    }
}
