<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'args',
        'ok',
        'code',
        'error',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'ok' => 'boolean',
            'code' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
