<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeSetting extends Model
{
    protected $fillable = [
        'allowed_creator_roles',
        'fs_number',
        'revision_number',
        'max_daily_hours_weekday',
        'max_weekly_hours',
        'compliance_mode',
        'compliance_notes',
    ];

    protected function casts(): array
    {
        return [
            'allowed_creator_roles' => 'array',
            'max_daily_hours_weekday' => 'float',
            'max_weekly_hours' => 'float',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'allowed_creator_roles' => ['HR', 'Manager', 'Supervisor'],
            'fs_number' => '05',
            'revision_number' => '01',
            'max_daily_hours_weekday' => 4.00,
            'max_weekly_hours' => 18.00,
            'compliance_mode' => 'warning',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedRoles(): array
    {
        $roles = $this->allowed_creator_roles;
        if (! is_array($roles) || empty($roles)) {
            return ['HR', 'Manager', 'Supervisor'];
        }

        return array_values(array_intersect($roles, ['HR', 'Manager', 'Supervisor']));
    }

    public function canUserCreate(User $user): bool
    {
        if ($user->hasAnyRole(['Superadmin', 'Admin'])) {
            return true;
        }

        $allowed = $this->getAllowedRoles();

        return ! empty($allowed) && $user->hasAnyRole($allowed);
    }
}
