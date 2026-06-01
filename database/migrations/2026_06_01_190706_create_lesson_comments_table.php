<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('cascade');
            $table->char('user_id', 36);
            $table->foreignId('parent_id')->nullable()->constrained('lesson_comments')->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('lesson_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_comments');
    }
};
