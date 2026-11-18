<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // طلبات الطباعة بالورقة: الكمية والسعر تحددها المطبعة (أو الإدارة اختيارياً)، فالمبلغ قد يكون فارغاً حتى التسعير
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
            $table->foreignId('priced_by')->nullable()->after('amount')->constrained('users')->nullOnDelete();
            $table->timestamp('priced_at')->nullable()->after('priced_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('priced_by');
            $table->dropColumn('priced_at');
        });
    }
};
