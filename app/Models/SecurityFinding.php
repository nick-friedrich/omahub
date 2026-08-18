<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'security_scan_id',
    'rule',
    'severity',
    'file',
    'line',
    'snippet',
    'description',
])]
class SecurityFinding extends Model
{
    public function scan(): BelongsTo
    {
        return $this->belongsTo(SecurityScan::class, 'security_scan_id');
    }
}
