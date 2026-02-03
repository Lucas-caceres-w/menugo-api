<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalClosures extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Relación con Local
     */
    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
