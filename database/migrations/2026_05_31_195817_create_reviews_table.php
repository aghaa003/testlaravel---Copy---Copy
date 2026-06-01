<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->char('user_id', 36);
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->timestamps();

            $table->foreign('course_id')
                ->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
