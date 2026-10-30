<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // physics, chemistry, botany, zoology
            $table->string('name');
            $table->integer('questions_per_full_test')->default(45);
            $table->integer('max_marks')->default(180);
            $table->timestamps();
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('class_level'); // 11th, 12th
            $table->string('unit_name');
            $table->string('name');
            $table->integer('weightage_percent')->default(5);
            $table->integer('expected_questions')->default(2);
            $table->boolean('high_yield')->default(true);
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('subjects');
    }
};

