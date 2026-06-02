<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->longText('solution')->nullable();
            $table->string('language')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->enum('status', ['submitted', 'graded'])->default('submitted');
            $table->boolean('is_completed')->default(false);
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'assignment_id']);
            $table->index(['user_id', 'status', 'is_completed']);
            $table->index(['assignment_id', 'score']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
