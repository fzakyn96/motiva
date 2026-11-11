<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'signature_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('signature_type', 30)->nullable()->after('remember_token');
                $table->longText('signature_data')->nullable()->after('signature_type');
                $table->string('signature_path')->nullable()->after('signature_data');
                $table->timestamp('signature_updated_at')->nullable()->after('signature_path');
            });
        }

        if (! Schema::hasTable('signature_settings')) {
            Schema::create('signature_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('allow_draw')->default(true);
                $table->boolean('allow_upload')->default(true);
                $table->boolean('allow_qrcode')->default(false);
                $table->string('document_display_mode', 40)->default('user_choice');
                $table->boolean('require_signature_before_sign')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_signatures')) {
            Schema::create('document_signatures', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->string('document_type');
                $table->unsignedBigInteger('document_id');
                $table->string('document_number')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('signer_name');
                $table->string('signer_position')->nullable();
                $table->string('signer_nip')->nullable();
                $table->string('role_type', 40)->default('approver');
                $table->string('signature_type', 30)->default('draw');
                $table->longText('signature_snapshot')->nullable();
                $table->timestamp('signed_at');
                $table->text('notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['document_type', 'document_id', 'role_type'], 'doc_sig_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
        Schema::dropIfExists('signature_settings');

        if (Schema::hasColumn('users', 'signature_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'signature_type',
                    'signature_data',
                    'signature_path',
                    'signature_updated_at',
                ]);
            });
        }
    }
};

