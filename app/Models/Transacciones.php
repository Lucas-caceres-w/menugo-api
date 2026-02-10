<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transacciones extends Model
{
    protected $table = 'transacciones';

    protected $fillable = [
        'total',
        'medio_pago',
        'payment_id',
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
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
    public function transaccionable()
    {
        return $this->morphTo();
    }
}
