<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->integer('progress')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
