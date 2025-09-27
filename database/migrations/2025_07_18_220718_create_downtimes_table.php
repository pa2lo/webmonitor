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
        Schema::create('downtimes', function (Blueprint $table) {
            $table->id();
			$table->foreignId('website_id')->constrained()->onDelete('cascade');
			$table->unsignedInteger('status');
			$table->unsignedBigInteger('from')->nullable();
			$table->unsignedBigInteger('to')->nullable();
			$table->json('attempts')->nullable();
			$table->longText('body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downtimes');
    }
};
