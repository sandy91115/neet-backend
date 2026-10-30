<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        // Topic names sourced from the official syllabus can exceed varchar(255).
        // Retain the safe text type on rollback to avoid truncating seeded topics.
    }
};
