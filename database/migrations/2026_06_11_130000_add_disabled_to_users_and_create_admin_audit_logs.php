<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'disabled')) {
            Schema::table('users', function (Blueprint $table) {
                // Distinct from `banned`: an admin-applied account disable. Either flag blocks login.
                $table->boolean('disabled')->default(false)->after('banned');
            });
        }

        if (! Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('admin_id', 36)->nullable();
                $table->string('action', 100);
                $table->string('target_type', 80);
                $table->string('target_id', 64);   // string: targets may be UUID users or bigint courses
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index('admin_id');
                $table->index(['target_type', 'target_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');

        if (Schema::hasColumn('users', 'disabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('disabled');
            });
        }
    }
};
