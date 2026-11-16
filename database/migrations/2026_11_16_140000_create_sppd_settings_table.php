<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sppd_settings', function (Blueprint $table) {
            $table->id();
            $table->string('form_filler_role', 50)->default('HR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sppd_settings');
    }
};

