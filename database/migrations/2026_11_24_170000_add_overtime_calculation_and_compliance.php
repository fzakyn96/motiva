<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('national_holidays')) {
            Schema::create('national_holidays', function (Blueprint $table): void {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'base_salary')) {
                $table->decimal('base_salary', 15, 2)->default(0)->after('identity_number');
            }
            if (! Schema::hasColumn('employees', 'position_allowance')) {
                $table->decimal('position_allowance', 15, 2)->default(0)->after('base_salary');
            }
            if (! Schema::hasColumn('employees', 'overtime_work_mode')) {
                $table->string('overtime_work_mode', 20)->default('non_shift')->after('position_allowance');
            }
        });

        Schema::table('overtime_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('overtime_settings', 'max_daily_hours_weekday')) {
                $table->decimal('max_daily_hours_weekday', 5, 2)->default(4.00)->after('revision_number');
            }
            if (! Schema::hasColumn('overtime_settings', 'max_weekly_hours')) {
                $table->decimal('max_weekly_hours', 5, 2)->default(18.00)->after('max_daily_hours_weekday');
            }
            if (! Schema::hasColumn('overtime_settings', 'compliance_mode')) {
                $table->string('compliance_mode', 20)->default('warning')->after('max_weekly_hours');
            }
            if (! Schema::hasColumn('overtime_settings', 'compliance_notes')) {
                $table->text('compliance_notes')->nullable()->after('compliance_mode');
            }
        });

        Schema::table('overtime_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('overtime_orders', 'work_mode')) {
                $table->string('work_mode', 20)->default('non_shift')->after('overtime_type');
            }
            if (! Schema::hasColumn('overtime_orders', 'is_shift_day_off')) {
                $table->boolean('is_shift_day_off')->default(false)->after('work_mode');
            }
            if (! Schema::hasColumn('overtime_orders', 'planned_hours')) {
                $table->decimal('planned_hours', 5, 2)->nullable()->after('is_shift_day_off');
            }
            if (! Schema::hasColumn('overtime_orders', 'day_type')) {
                $table->string('day_type', 5)->nullable()->after('planned_hours');
            }
            if (! Schema::hasColumn('overtime_orders', 'actual_hours')) {
                $table->decimal('actual_hours', 5, 2)->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('overtime_orders', 'hourly_tariff')) {
                $table->decimal('hourly_tariff', 15, 4)->nullable()->after('actual_hours');
            }
            if (! Schema::hasColumn('overtime_orders', 'multiplier')) {
                $table->decimal('multiplier', 8, 2)->nullable()->after('hourly_tariff');
            }
            if (! Schema::hasColumn('overtime_orders', 'overtime_pay')) {
                $table->decimal('overtime_pay', 15, 2)->nullable()->after('multiplier');
            }
            if (! Schema::hasColumn('overtime_orders', 'compliance_status')) {
                $table->string('compliance_status', 20)->default('ok')->after('overtime_pay');
            }
            if (! Schema::hasColumn('overtime_orders', 'compliance_warnings')) {
                $table->text('compliance_warnings')->nullable()->after('compliance_status');
            }
            if (! Schema::hasColumn('overtime_orders', 'compliance_override_reason')) {
                $table->text('compliance_override_reason')->nullable()->after('compliance_warnings');
            }
        });

        if (! Schema::hasTable('overtime_recaps')) {
            Schema::create('overtime_recaps', function (Blueprint $table): void {
                $table->id();
                $table->string('recap_number')->nullable();
                $table->unsignedTinyInteger('period_month');
                $table->unsignedSmallInteger('period_year');
                $table->string('title');
                $table->foreignId('department_id')->nullable()->constrained('departments')->noActionOnDelete();
                $table->foreignId('creator_id')->nullable()->constrained('users')->noActionOnDelete();
                $table->decimal('total_shift_amount', 15, 2)->default(0);
                $table->decimal('total_non_shift_amount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);
                $table->string('status', 30)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('overtime_recap_items')) {
            Schema::create('overtime_recap_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('overtime_recap_id')->constrained('overtime_recaps')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->noActionOnDelete();
                $table->decimal('shift_hours', 8, 2)->default(0);
                $table->decimal('shift_amount', 15, 2)->default(0);
                $table->decimal('non_shift_hours', 8, 2)->default(0);
                $table->decimal('non_shift_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_recap_items');
        Schema::dropIfExists('overtime_recaps');

        Schema::table('overtime_orders', function (Blueprint $table): void {
            $cols = [
                'work_mode', 'is_shift_day_off', 'planned_hours', 'day_type',
                'actual_hours', 'hourly_tariff', 'multiplier', 'overtime_pay',
                'compliance_status', 'compliance_warnings', 'compliance_override_reason',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('overtime_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('overtime_settings', function (Blueprint $table): void {
            foreach (['max_daily_hours_weekday', 'max_weekly_hours', 'compliance_mode', 'compliance_notes'] as $col) {
                if (Schema::hasColumn('overtime_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('employees', function (Blueprint $table): void {
            foreach (['base_salary', 'position_allowance', 'overtime_work_mode'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('national_holidays');
    }
};
