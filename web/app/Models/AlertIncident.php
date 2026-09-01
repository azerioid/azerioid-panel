<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertIncident extends Model
{
    protected $fillable = [
        'rule_key', 'subject', 'status', 'severity', 'message',
        'opened_at', 'resolved_at', 'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }
}
