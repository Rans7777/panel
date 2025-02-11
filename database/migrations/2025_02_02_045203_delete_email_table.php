<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            if (Schema::hasTable('users_temp')) {
                Schema::drop('users_temp');
            }

            $hasPassword = Schema::hasColumn('users', 'password');
            
            Schema::create('users_temp', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('password');
                $table->timestamps();
            });
            
            if ($hasPassword) {
                DB::statement('INSERT INTO users_temp (id, name, password, created_at, updated_at) SELECT id, name, password, created_at, updated_at FROM users');
            } else {
                DB::statement('INSERT INTO users_temp (id, name, password, created_at, updated_at) SELECT id, name, "", created_at, updated_at FROM users');
            }
            
            Schema::drop('users');
            Schema::rename('users_temp', 'users');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['email']);
                $table->dropColumn(['email', 'email_verified_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            if (!Schema::hasColumn('users', 'email')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email')->unique()->nullable();
                });
            }

            if (!Schema::hasColumn('users', 'email_verified_at')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email_verified_at')->nullable();
                });
            }
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->unique();
                $table->string('email_verified_at')->nullable();
            });
        }
    }
};
