<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_likes', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->unsignedBigInteger('repository_id');
            $table->timestamps();

            $table->unique(['user_id', 'repository_id']);

            $table->foreign('user_id')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('repository_id')
                ->references('id')->on('repositories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_likes');
    }
};
