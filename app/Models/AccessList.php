<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessList extends Model
{
    protected $fillable = [
        'name',
        'ips',
        'description',
    ];

    protected $casts = [
        'ips' => 'array',
    ];

    public function applicationSettings()
    {
        return $this->hasMany(ApplicationSetting::class);
    }

    public function getIpsAttribute($value)
    {
        return is_array($value) ? $value : json_decode($value, true) ?? [];
    }

    public function setIpsAttribute($value)
    {
        $this->attributes['ips'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getIpsFormattedAttribute(): string
    {
        return implode(', ', $this->ips ?? []);
    }
}