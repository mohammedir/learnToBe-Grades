<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مراقبة الاختبار: عدد مرات الخروج من صفحة الاختبار ومدته، ومحاولات النسخ/لقطة الشاشة
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedInteger('leave_count')->default(0)->after('needs_review');
            $table->unsignedInteger('leave_seconds')->default(0)->after('leave_count');
            $table->unsignedInteger('copy_attempts')->default(0)->after('leave_seconds');
            $table->unsignedInteger('screenshot_attempts')->default(0)->after('copy_attempts');
            $table->json('integrity_log')->nullable()->after('screenshot_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['leave_count', 'leave_seconds', 'copy_attempts', 'screenshot_attempts', 'integrity_log']));
    }
};
