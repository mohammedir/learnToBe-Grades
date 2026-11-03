<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أنواع أسئلة جديدة: text (إجابة نصية) و number (إجابة رقمية)، وصورة لكل سؤال
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('correct', 20)->nullable()->change();
            $table->json('meta')->nullable()->after('correct');      // number: value/tolerance/unit — text: accepted/model
            $table->string('image_path')->nullable()->after('body');
        });

        // نشر النتائج للطلاب (يتحكم به المدرس أو الإدارة)
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('results_published')->default(false)->after('is_published');
        });

        // درجة كل سؤال في المحاولة، والتصحيح اليدوي
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('awarded')->nullable()->after('answers');
            $table->boolean('needs_review')->default(false)->after('late');
            $table->foreignId('reviewed_by')->nullable()->after('needs_review')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        // إيقاف حساب الطالب في البوابة مع ذكر السبب
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('last_login_at');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->foreignId('suspended_by')->nullable()->after('suspension_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['awarded', 'needs_review', 'reviewed_at']);
        });
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn('results_published'));
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropColumn(['meta', 'image_path']));
    }
};
