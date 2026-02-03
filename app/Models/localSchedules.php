<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalSchedules extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_id',
        'day_of_week',
        'opens_at',
        'is_closed',
        'closes_at',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];


    /**
     * Relación con Local
     */
    public function local()
    {
        return $this->belongsTo(Local::class);
    }

    /**
     * Helper: nombre del día (opcional, útil para UI)
     */
    public function getDayNameAttribute(): string
    {
        return [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ][$this->day_of_week];
    }
}
