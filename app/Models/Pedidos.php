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
        return $this->hasMany(Transacciones::class);
    }
    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
