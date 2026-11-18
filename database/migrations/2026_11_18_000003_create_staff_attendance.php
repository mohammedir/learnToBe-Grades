<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // نقاط التسجيل: رمز QR مطبوع (مثلاً عند البوابة أو غرفة المدرسين)
        Schema::create('attendance_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token', 48)->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('radius')->default(150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // تسجيلات الحضور والمغادرة (تُرفع لاحقاً إن سُجّلت بدون إنترنت)
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 3); // in | out
            $table->dateTime('occurred_at')->index();   // الوقت المعتمد (بعد تصحيح فرق ساعة الجهاز)
            $table->dateTime('device_at')->nullable();  // الوقت كما سجّله الجهاز
            $table->integer('skew_seconds')->default(0);
            $table->dateTime('synced_at')->nullable();
            $table->boolean('offline')->default(false); // رُفع متأخراً (سُجّل بلا إنترنت)
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->integer('distance')->nullable();    // المسافة عن نقطة التسجيل بالمتر
            $table->string('source', 10)->default('qr'); // qr | manual
            $table->string('client_uuid', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_points');
    }
};
