<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إظهار جوال المدرس (واتساب) لطلابه في بوابة الطالب
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_phone_to_students')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('show_phone_to_students'));
    }
};
