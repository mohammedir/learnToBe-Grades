<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حساب الطالب: الدخول برقم الهوية وكلمة مرور
        Schema::table('students', function (Blueprint $table) {
            $table->string('password')->nullable()->after('birth_date');
            $table->boolean('must_change_password')->default(true)->after('password');
            $table->timestamp('password_issued_at')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('password_issued_at');
            $table->rememberToken();
        });

        // إعدادات عامة (مثل نشر الدرجات للطلاب)
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['password', 'must_change_password', 'password_issued_at', 'last_login_at', 'remember_token']);
        });
    }
};
