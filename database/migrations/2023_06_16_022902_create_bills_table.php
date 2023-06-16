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
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->date('month');
            $table->float('amount');
            $table->enum('status', ['pending', 'paid', 'due']);
            $table->string('pdf');
            $table->timestamps();
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
