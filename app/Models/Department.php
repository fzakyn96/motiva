<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['organization_id', 'name', 'code'];

    protected static function booted(): void
    {
        static::deleting(function (Department $department): void {
            if (\Illuminate\Support\Facades\Schema::hasTable('work_areas')) {
                $areaIds = WorkArea::where('department_id', $department->id)->pluck('id');
                if ($areaIds->isNotEmpty()) {
                    Employee::whereIn('work_area_id', $areaIds)->update(['work_area_id' => null]);
                    WorkArea::whereIn('id', $areaIds)->delete();
                }
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function workAreas()
    {
        return $this->hasMany(WorkArea::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
