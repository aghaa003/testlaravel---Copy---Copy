<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_submissions', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->unsignedBigInteger('challenge_id');
            $table->text('solution');
            $table->string('language');
            $table->boolean('success')->default(false);
            $table->integer('points_earned')->default(0);
            $table->integer('score')->default(0);
            $table->string('message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('challenge_id')
                ->references('id')->on('challenges')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_submissions');
    }
};
