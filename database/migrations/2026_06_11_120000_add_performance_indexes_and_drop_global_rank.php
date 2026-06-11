<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leaderboard ordering + live rank computation (where points > X) scan this column.
        Schema::table('users', function (Blueprint $table) {
            $table->index('points');
            // global_rank was never written anywhere — rank is computed live in
            // UserController::show. Drop the perpetually-stale denormalized column.
            $table->dropColumn('global_rank');
        });

        // Course filtering by category/language.
        Schema::table('courses', function (Blueprint $table) {
            $table->index('category');
            $table->index('language');
        });

        // Challenge filtering by category/difficulty.
        Schema::table('challenges', function (Blueprint $table) {
            $table->index('category');
            $table->index('difficulty');
        });

        // Example filtering by category.
        Schema::table('examples', function (Blueprint $table) {
            $table->index('category');
        });

        // Public repo listings + per-owner profile listings (owner_id had a FK but no index).
        Schema::table('repositories', function (Blueprint $table) {
            $table->index(['visibility', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['points']);
            $table->integer('global_rank')->nullable();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['language']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['difficulty']);
        });

        Schema::table('examples', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'owner_id']);
        });
    }
};
