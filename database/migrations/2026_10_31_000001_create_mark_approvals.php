<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اعتماد الدرجات: (شعبة + مادة + عنصر درجة) معتمد = مقفل على التعديل
        Schema::create('mark_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at');
            $table->unique(['section_id', 'subject_id', 'assessment_component_id'], 'mark_approvals_cell');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_approvals');
    }
};
