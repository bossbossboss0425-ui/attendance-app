<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proposal_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_correction_request_id')->constrained('stamp_correction_requests')->onDelete('cascade');
            $table->time('break_in');
            $table->time('break_out')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_breaks');
    }
};
