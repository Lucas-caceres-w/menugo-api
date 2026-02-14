<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidos extends Model
{
    protected $fillable = [
        'local_id',
        'client_name',
        'client_phone',
        'client_address',
        'client_lat',
        'costo_envio',
        'tipo_entrega',
        'client_lng',
        'observacion',
        'payment_method',
        'payment_status',
        'estado',
        'total',
    ];

    public function items()
    {
        return $this->hasMany(PedidoItems::class, 'pedido_id');
    }
    public function transacciones()
    {
        return $this->morphMany(Transacciones::class, 'transaccionable');
    }
    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
