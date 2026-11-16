<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppdSetting extends Model
{
    protected $fillable = [
        'form_filler_role',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'form_filler_role' => 'HR',
        ]);
    }

    public function isStaffFiller(): bool
    {
        return $this->form_filler_role === 'Staff';
    }

    public function isHrFiller(): bool
    {
        return $this->form_filler_role !== 'Staff';
    }
}

