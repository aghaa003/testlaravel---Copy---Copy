<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('thumbnail_url')->nullable();

            // تعريف الحقل
            $table->char('owner_id', 36);

            $table->json('technologies');
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->boolean('is_draft')->default(false);
            $table->integer('likes_count')->default(0);
            $table->integer('stars_count')->default(0);
            $table->integer('forks_count')->default(0);
            $table->string('project_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('live_demo_url')->nullable();
            $table->longText('code_files_urls')->nullable();
            $table->longText('pdf_files_urls')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('source_project')->nullable();
            $table->timestamps();

            // إنشاء قيد المفتاح الأجنبي (Foreign Key Constraint)
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
