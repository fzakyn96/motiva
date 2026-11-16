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
        Schema::create('memo_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memo_id')->nullable()->index();
            $table->string('nomor_memo')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('agenda')->nullable();
            $table->string('to_recipient')->nullable();
            $table->string('department_name')->nullable();
            $table->string('creator_name')->nullable();
            $table->date('memo_date')->nullable();
            $table->string('memo_status')->default('approved');
            $table->string('action')->default('deleted_approved_memo');
            $table->text('deletion_reason');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->index();
            $table->string('deleted_by_name');
            $table->string('deleted_by_role')->nullable();
            $table->json('memo_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memo_logs');
    }
};

