<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تعطيل مزايا محددة من البوابة لطالب بعينه مع السبب
        Schema::table('students', function (Blueprint $table) {
            $table->json('portal_features_off')->nullable()->after('suspension_reason');
            $table->string('portal_off_reason', 500)->nullable()->after('portal_features_off');
            $table->timestamp('portal_off_at')->nullable()->after('portal_off_reason');
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['portal_features_off', 'portal_off_reason', 'portal_off_at']));
    }
};
