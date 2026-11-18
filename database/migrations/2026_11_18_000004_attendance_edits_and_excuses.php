<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تعديل الإدارة لوقت الحضور/المغادرة مع حفظ الوقت الأصلي
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dateTime('original_at')->nullable()->after('occurred_at');
            $table->foreignId('edited_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('edited_at')->nullable()->after('edited_by');
        });

        // الأعذار والإجازات: غياب بعذر (مجاز) أو تأخير/خروج بإذن، مع ملاحظة اختيارية
        Schema::create('attendance_excuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_excuses');
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['original_at', 'edited_at']);
        });
    }
};
