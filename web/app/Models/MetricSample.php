<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricSample extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sampled_at', 'load1', 'ram_used', 'ram_total', 'disk_percent',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'load1' => 'float',
            'ram_used' => 'integer',
            'ram_total' => 'integer',
            'disk_percent' => 'integer',
        ];
    }
}
