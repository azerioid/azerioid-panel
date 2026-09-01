<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupJob extends Model
{
    protected $fillable = [
        'kind', 'name', 'object_key', 'status', 'size', 'duration_ms', 'error',
    ];
}
