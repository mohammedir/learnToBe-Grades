<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الصفوف الدراسية (بستان، تمهيدي، الصف الأول ...)
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // الشعب: كل صف فيه شعبة واحدة أو أكثر
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('period')->nullable(); // صباحي / مسائي
            $table->timestamps();
            $table->unique(['grade_level_id', 'name']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('gender', ['male', 'female']);
            $table->string('national_id')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ربط المدرس بالشعبة والمادة: لكل شعبة ومادة مدرس واحد فقط
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['section_id', 'subject_id']);
        });

        // عناصر الدرجة (الشهري الأول، الشهري الثاني، المشاركة ...)
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('max_score', 6, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'subject_id', 'assessment_component_id'], 'marks_unique_cell');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grade_levels');
    }
};
