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
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
			$table->foreignId('website_id')->constrained()->cascadeOnDelete();
			$table->string('status', 100);
			$table->boolean('phrase')->default(false);
			$table->integer('duration')->nullable();
			$table->boolean('redirected')->nullable();
			$table->string('server', 10)->nullable();
			$table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
