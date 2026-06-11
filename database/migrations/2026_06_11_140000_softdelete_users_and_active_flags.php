<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('courses', 'is_active')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }

        if (! Schema::hasColumn('challenges', 'is_active')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
        if (Schema::hasColumn('courses', 'is_active')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('challenges', 'is_active')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
