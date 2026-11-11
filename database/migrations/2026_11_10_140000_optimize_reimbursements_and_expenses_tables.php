<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('reimbursement_number')->nullable()->after('id');
            $table->string('title')->nullable()->after('employee_id');
            $table->text('notes')->nullable()->after('title');
            $table->decimal('total_amount', 15, 2)->default(0)->after('notes');
            $table->decimal('total_claim_amount', 15, 2)->default(0)->after('total_amount');
            $table->dateTime('submitted_at')->nullable()->after('status');
            $table->dateTime('approved_at')->nullable()->after('submitted_at');
            $table->dateTime('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_note')->nullable()->after('rejected_at');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('expense_type');
            $table->string('description', 500)->nullable()->after('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['transaction_date', 'description']);
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn([
                'reimbursement_number',
                'title',
                'notes',
                'total_amount',
                'total_claim_amount',
                'submitted_at',
                'approved_at',
                'rejected_at',
                'rejection_note',
            ]);
        });
    }
};
