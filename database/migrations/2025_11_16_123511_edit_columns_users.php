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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');

            $table->dropForeign(['zone_id']);
            $table->renameColumn('zone_id', 'zone_wispro_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->unsignedBigInteger('plan_id')->nullable();

            $table->foreignId('zone_wispro_id')->nullable()->constrained('zones');
            $table->unsignedBigInteger('zone_wispro_id')->nullable();
            $table->renameColumn('zone_wispro_id', 'zone_id');
        });
    }
};
