<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جوال ولي الأمر/الطالب: رقم أساسي ورقم إضافي (بصيغة 05xxxxxxxx)
        Schema::table('students', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('birth_date')->index();
            $table->string('phone2', 20)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['phone', 'phone2']));
    }
};
