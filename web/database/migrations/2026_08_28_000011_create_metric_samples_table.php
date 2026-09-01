<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_samples', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sampled_at')->index();
            $table->float('load1')->default(0);
            $table->unsignedBigInteger('ram_used')->default(0);
            $table->unsignedBigInteger('ram_total')->default(0);
            $table->unsignedTinyInteger('disk_percent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_samples');
    }
};
