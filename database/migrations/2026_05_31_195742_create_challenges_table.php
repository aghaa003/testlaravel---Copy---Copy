<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('input_format')->nullable();
            $table->text('output_format')->nullable();
            $table->longText('examples')->nullable();
            $table->text('constraints')->nullable();
            $table->integer('time_limit')->default(2);
            $table->longText('tags')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->string('category');
            $table->string('section', 100)->default('algorithms');
            $table->integer('points')->default(0);
            $table->integer('total_submissions')->default(0);
            $table->float('success_rate')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
