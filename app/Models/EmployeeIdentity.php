<?php

namespace App\Models;

use App\Enums\IdentityProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'provider',
        'external_id',
        'username',
        'metadata',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => IdentityProvider::class,
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}