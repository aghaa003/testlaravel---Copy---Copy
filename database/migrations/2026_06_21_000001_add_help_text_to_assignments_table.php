<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assignments', 'help_text')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->text('help_text')->nullable()->after('requirements');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assignments', 'help_text')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->dropColumn('help_text');
            });
        }
    }
};
