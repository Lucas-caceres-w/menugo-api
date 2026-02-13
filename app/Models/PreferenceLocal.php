<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenceLocal extends Model
{
    protected $fillable = [
        'id',
        'local_id',
        'preference_id',
        'type'
    ];

    public function local()
    {
        return $this->belongsTo(Local::class);
    }
}
