<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->index();
        });

        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_test_id')->constrained()->cascadeOnDelete();
            $table->json('responses');
            $table->json('subject_breakdowns')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('incorrect_count')->default(0);
            $table->unsignedSmallInteger('unattempted_count')->default(0);
            $table->integer('total_score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->decimal('accuracy_percent', 5, 2)->default(0);
            $table->unsignedInteger('time_taken_seconds')->default(0);
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
