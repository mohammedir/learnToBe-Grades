<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حالة قيد الطالب: منتظم / متوقف عن الدراسة / منسحب / منقول. غير المنتظم يختفي من الكشوف دون حذف درجاته
        Schema::table('students', function (Blueprint $table) {
            $table->string('enrollment_status', 16)->default('active')->after('is_active')->index();
            $table->string('status_reason', 500)->nullable()->after('enrollment_status');
            $table->date('status_date')->nullable()->after('status_reason');
            $table->foreignId('status_changed_by')->nullable()->after('status_date')->constrained('users')->nullOnDelete();
        });

        DB::table('students')->where('is_active', false)->update([
            'enrollment_status' => 'stopped',
            'status_reason' => 'أُوقف قبل إضافة حالات الطالب',
        ]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn(['enrollment_status', 'status_reason', 'status_date']);
        });
    }
};
