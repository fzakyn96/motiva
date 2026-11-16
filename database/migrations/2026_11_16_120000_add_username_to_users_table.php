<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->after('email');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX users_username_unique ON users (username) WHERE username IS NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX users_username_unique ON users');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};

