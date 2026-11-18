<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_daily_stats', function (Blueprint $table) {
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedBigInteger('attempt_count');
            $table->unsignedBigInteger('success_count');
            $table->bigInteger('duration_sum_ms');
            $table->unsignedBigInteger('duration_count');
            $table->primary(['website_id', 'date']);
        });
    }

    public function down(): void
    {
        app(SettingsService::class)->delete('website_stats_completed_through');
        Schema::dropIfExists('website_daily_stats');
    }
};
