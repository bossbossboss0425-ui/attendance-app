<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('break_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->onDelete('cascade');
            $table->time('break_in');
            $table->time('break_out')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_records');
    }
};