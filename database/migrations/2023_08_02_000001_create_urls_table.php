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

            $table->morphs('urlable');

            $urlColumn = $table->string('full_url', 2000)->index();

            // Apply case-sensitive collation per driver where applicable
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $urlColumn->collation('utf8mb4_bin');
            } elseif ($driver === 'sqlsrv') {
                $urlColumn->collation('SQL_Latin1_General_CP1_CS_AS');
            }

            $table->string('collection')->nullable()->index();

            $table->unsignedInteger('version')->default(1);

            $table->softDeletes();
            $table->timestamps();

            $table->unique([
                'urlable_type',
                'urlable_id',
                'version'
            ], 'URL_UNIQUE');
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
