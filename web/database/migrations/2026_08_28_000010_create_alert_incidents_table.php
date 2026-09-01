<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 64);
            $table->string('subject', 190);
            $table->string('status', 16)->default('open');
            $table->string('severity', 16)->default('high');
            $table->text('message');
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
            $table->index(['rule_key', 'subject', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_incidents');
    }
};
