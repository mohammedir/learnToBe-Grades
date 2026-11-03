<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin = مدير عام (كل الصلاحيات) | staff = مدير/موظف بصلاحيات محددة | teacher = مدرس
            $table->string('role', 20)->default('teacher')->change();
            $table->string('job_title')->nullable()->after('name');
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'permissions']);
        });
    }
};
