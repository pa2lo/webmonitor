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
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
			$table->boolean('active')->default(true);
			$table->string('name', 100);
			$table->string('url', 100);
			$table->string('phrase', 100)->nullable();
			$table->string('status', 100)->nullable();
			$table->unsignedBigInteger('attempts')->default(0);
			$table->string('server', 10)->default('php');
			$table->tinyInteger('requests')->default(3);
			$table->tinyInteger('timeout')->default(10);
			$table->unsignedBigInteger('checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
