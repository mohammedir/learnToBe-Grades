<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ملاحظات المدرس للطالب
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10)->default('note'); // praise | note | alert
            $table->text('body');
            $table->timestamps();
        });

        // كلمة المرور المؤقتة مشفّرة لتراها الإدارة حتى يغيّرها الطالب
        Schema::table('students', function (Blueprint $table) {
            $table->text('initial_password')->nullable()->after('password');
        });

        // الاختبارات الإلكترونية
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('show_answers')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // mcq | tf
            $table->text('body');
            $table->json('options')->nullable();
            $table->string('correct', 20);
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->json('answers')->nullable();
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->default(0);
            $table->boolean('late')->default(false);
            $table->timestamps();
            $table->unique(['quiz_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('initial_password'));
        Schema::dropIfExists('student_notes');
    }
};
