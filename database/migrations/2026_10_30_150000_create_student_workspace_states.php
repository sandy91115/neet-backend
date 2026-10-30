<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_workspace_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('syllabus')->nullable();
            $table->json('official_syllabus_progress')->nullable();
            $table->json('attempts')->nullable();
            $table->json('series_tests')->nullable();
            $table->json('daily_tasks')->nullable();
            $table->json('mistakes')->nullable();
            $table->json('bookmarks')->nullable();
            $table->json('practice_selections')->nullable();
            $table->json('revealed_solutions')->nullable();
            $table->json('saved_practice_mistakes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_workspace_states');
    }
};
