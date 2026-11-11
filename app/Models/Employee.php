<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'department_id',
        'position_id',
        'manager_id',
        'name',
        'identity_number',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Employee $employee) {
            // 1. Lepaskan relasi atasan (manager_id) pada bawahan
            static::where('manager_id', $employee->id)->update(['manager_id' => null]);

            // 2. Set null approver_employee_id pada approval_steps yang pernah diperiksa
            \App\Models\ApprovalStep::where('approver_employee_id', $employee->id)
                ->update(['approver_employee_id' => null]);

            // 3. Hapus approval requests yang diajukan oleh pegawai ini beserta step-nya
            \App\Models\ApprovalRequest::where('requester_employee_id', $employee->id)->each(function ($req) {
                \App\Models\ApprovalStep::where('approval_request_id', $req->id)->delete();
                $req->delete();
            });

            // 4. Hapus SPPD milik pegawai ini
            \App\Models\Sppd::where('employee_id', $employee->id)->delete();

            // 5. Hapus Reimbursement milik pegawai ini (beserta expenses)
            \App\Models\Reimbursement::where('employee_id', $employee->id)->each(function ($reimb) {
                \App\Models\Expense::where('reimbursement_id', $reimb->id)->delete();
                $reimb->delete();
            });

            // 6. Hapus Memo yang dibuat oleh pegawai ini
            \App\Models\Memo::where('creator_employee_id', $employee->id)->each(function ($memo) {
                \App\Models\BusinessTrip::where('memo_id', $memo->id)->each(function ($trip) {
                    \App\Models\Sppd::where('business_trip_id', $trip->id)->delete();
                    $trip->delete();
                });
                $memo->delete();
            });
        });

        static::deleted(function (Employee $employee) {
            // Hapus akun user terkait setelah employee terhapus (kecuali jika user Superadmin)
            if ($employee->user_id) {
                $user = \App\Models\User::find($employee->user_id);
                if ($user && ! $user->hasRole('Superadmin')) {
                    $user->delete();
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }
}
