<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(config('url.tables.url'), function (Blueprint $table) {
            $table->id();

            // Keep morphs aligned with bigint IDs (unsigned on MySQL)
            $table->morphs('urlable');

            // Determine driver-specific settings
            $driver = Schema::getConnection()->getDriverName();
            $isMySql = $driver === 'mysql';     // also covers MariaDB (same driver name)
            $isSqlsrv = $driver === 'sqlsrv';

            // Cap length at 191 for MySQL/MariaDB to avoid index-length issues on utf8mb4.
            $urlLength = $isMySql ? min((int)config('url.url_long'), 191) : (int)config('url.url_long');

            // Create the url column
            $urlColumn = $table->string('url', $urlLength);

            // Apply case-sensitive collation per driver where applicable
            if ($isMySql) {
                // MySQL/MariaDB case-sensitive
                $urlColumn->collation('utf8mb4_bin');
            } elseif ($isSqlsrv) {
                // SQL Server case-sensitive
                // Ensure this collation exists on your server or adjust as needed.
                $urlColumn->collation('SQL_Latin1_General_CP1_CS_AS');
            }
            // On PostgreSQL, column collation here is typically ignored; default is case-sensitive.

            // Reverse lookup by URL can benefit from an index
            $table->index('url', 'url_lookup_idx');

            $table->timestamps();

            // Enforce per-record uniqueness of a given URL
            $table->unique(
                ['urlable_type', 'urlable_id', 'url'],
                'URL_UNIQUE'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('url.tables.url'));
    }
};
