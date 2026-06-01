<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('thumbnail_url')->nullable();
            $table->string('category');
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->string('language')->nullable();

            // تعريف الحقل
            $table->char('creator_id', 36);

            $table->float('average_rating')->default(0);
            $table->integer('total_reviews')->default(0);
            $table->integer('total_lessons')->default(0);
            $table->integer('total_enrollments')->default(0);
            $table->timestamps();

            // إنشاء قيد المفتاح الأجنبي (Foreign Key Constraint)
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
