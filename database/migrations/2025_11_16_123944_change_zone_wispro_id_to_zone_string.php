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
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['zone_wispro_id']);
            });
        } catch (\Exception $e) {
            // Si la llave foránea no existe, continuar
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('zone_wispro_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('zone_wispro_id', 'zone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('zone', 'zone_wispro_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_wispro_id')->nullable()->change();
        });

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('zone_wispro_id')->references('id')->on('zones');
            });
        } catch (\Exception $e) {
            // Si hay error al crear la llave foránea, continuar
        }
    }
};
