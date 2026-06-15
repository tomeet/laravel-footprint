<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('footprint.footprint_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(config('footprint.user_foreign_key'))->index()->comment('user_id');
            $table->morphs('footprintable');
            $table->string('title')->nullable();              // 快照标题
            $table->string('image')->nullable();              // 快照图片
            $table->json('meta')->nullable();                 // 扩展信息（价格、分类等）
            $table->timestamp('viewed_at');                   // 浏览时间
            $table->timestamps();

            $table->unique([config('footprint.user_foreign_key'), 'footprintable_type', 'footprintable_id'], 'footprints_unique');
            $table->index([config('footprint.user_foreign_key'), 'viewed_at'],'footprints_user_viewed_idx');
            $table->index(['footprintable_type', 'footprintable_id', 'viewed_at'], 'footprints_footprintable_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('footprint.footprints_table'));
    }
};
