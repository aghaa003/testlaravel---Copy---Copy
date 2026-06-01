<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->onDelete('cascade');
            $table->char('user_id', 36);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_likes');
    }
};
