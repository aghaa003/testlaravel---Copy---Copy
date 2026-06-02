<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('question');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->string('language')->nullable();
            $table->unsignedInteger('assignment_order')->default(1);
            $table->unsignedInteger('points')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamp('due_date')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'assignment_order']);
            $table->index(['language', 'difficulty']);
            $table->index(['is_active', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
