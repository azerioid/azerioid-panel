<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('component_operations', function (Blueprint $table) {
            $table->json('options')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('component_operations', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
