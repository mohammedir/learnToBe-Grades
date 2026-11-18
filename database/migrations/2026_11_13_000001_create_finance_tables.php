<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المالية ← المشتريات: الموردون (المطبعة/المكتبة)، طلبات الشراء، سجل الحالات، الدفعات
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('default_page_price', 10, 3)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // حساب دخول للمورد (المطبعة) لمتابعة طلباته وتغيير حالتها
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            // من يستلم الطلبية من المطبعة: مستخدم في النظام (مدرس/موظف) أو اسم يُكتب يدوياً
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            // التسعير: fixed سعر ثابت | per_page بالورقة | per_unit بالقطعة
            $table->string('pricing', 10)->default('fixed');
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedInteger('copies')->nullable();
            $table->decimal('unit_price', 10, 3)->nullable();
            $table->decimal('amount', 12, 2);
            // pending بانتظار المطبعة | preparing قيد التجهيز | ready جاهز | received تم الاستلام | cancelled ملغى
            $table->string('status', 12)->default('pending')->index();
            $table->date('needed_by')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_at')->nullable();
            $table->foreignId('status_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('method', 20)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_order_events');
        Schema::dropIfExists('purchase_orders');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::dropIfExists('suppliers');
    }
};
