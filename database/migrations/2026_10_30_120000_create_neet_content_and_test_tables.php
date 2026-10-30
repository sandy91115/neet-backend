<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->string('syllabus_version')->default('NEET UG 2026')->after('slug');
            $table->string('official_unit_code')->nullable()->index()->after('syllabus_version');
            $table->string('weightage_basis')->default('unverified')->after('expected_questions');
            $table->unsignedSmallInteger('weightage_reference_year')->nullable()->after('weightage_basis');
            $table->string('source_url', 2048)->nullable()->after('high_yield');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('topic_name')->nullable();
            $table->string('question_type')->default('single_correct');
            $table->string('difficulty')->default('medium')->index();
            $table->longText('stem');
            $table->json('options');
            $table->char('correct_option', 1);
            $table->longText('explanation')->nullable();
            $table->string('ncert_reference')->nullable();
            $table->boolean('is_pyq')->default(false)->index();
            $table->unsignedSmallInteger('pyq_year')->nullable()->index();
            $table->string('source_label')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_page')->nullable();
            $table->string('source_question_number')->nullable();
            $table->string('review_status')->default('pending_review')->index();
            $table->string('content_license')->nullable();
            $table->string('content_fingerprint', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['subject_id', 'chapter_id', 'review_status']);
        });

        Schema::create('study_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('provider');
            $table->string('resource_type')->default('reference');
            $table->text('description')->nullable();
            $table->string('url', 2048);
            $table->char('url_hash', 64)->unique();
            $table->string('license_note')->nullable();
            $table->boolean('is_official')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('test_blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('cadence')->default('custom')->index(); // weekly, monthly, custom
            $table->string('test_category')->default('custom');
            $table->json('config');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('positive_marks')->default(4);
            $table->unsignedSmallInteger('negative_marks')->default(1);
            $table->boolean('pyq_only')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('generated_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->nullable()->constrained('test_blueprints')->nullOnDelete();
            $table->string('title');
            $table->string('test_category')->index();
            $table->string('generation_mode')->index();
            $table->json('generation_config');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedSmallInteger('positive_marks')->default(4);
            $table->unsignedSmallInteger('negative_marks')->default(1);
            $table->string('status')->default('ready')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('generated_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['generated_test_id', 'question_id']);
            $table->unique(['generated_test_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_test_questions');
        Schema::dropIfExists('generated_tests');
        Schema::dropIfExists('test_blueprints');
        Schema::dropIfExists('study_resources');
        Schema::dropIfExists('questions');

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn([
                'syllabus_version',
                'official_unit_code',
                'weightage_basis',
                'weightage_reference_year',
                'source_url',
            ]);
        });
    }
};
