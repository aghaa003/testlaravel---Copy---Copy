<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->char('from_user_id', 36)->nullable();
            $table->string('from_user_name')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type', 100)->nullable();
            $table->bigInteger('entity_id')->nullable();
            $table->string('entity_title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
