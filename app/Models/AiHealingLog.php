<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiHealingLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
