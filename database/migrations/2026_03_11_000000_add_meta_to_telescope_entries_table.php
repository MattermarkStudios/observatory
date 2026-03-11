<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->getConnection())
            ->table('telescope_entries', function (Blueprint $table) {
                $table->jsonb('meta')->nullable();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->table('telescope_entries', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
    }
};
