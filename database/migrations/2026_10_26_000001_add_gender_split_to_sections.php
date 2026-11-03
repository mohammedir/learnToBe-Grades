<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // صف مقسوم = شعبة إناث وشعبة ذكور منفصلتان
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->boolean('split_by_gender')->default(false)->after('sort_order');
        });

        // جنس الشعبة: male / female / null = مختلطة
        Schema::table('sections', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('sections', fn (Blueprint $table) => $table->dropColumn('gender'));
        Schema::table('grade_levels', fn (Blueprint $table) => $table->dropColumn('split_by_gender'));
    }
};
