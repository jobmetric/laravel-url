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
        Schema::create(config('url.tables.slug'), function (Blueprint $table) {
            $table->id();

            $table->morphs('slugable');

            $slugColumn = $table->string('slug', 100)->index();

            // Apply case-sensitive collation per driver where applicable
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                $slugColumn->collation('utf8mb4_bin');
            } elseif ($driver === 'sqlsrv') {
                $slugColumn->collation('SQL_Latin1_General_CP1_CS_AS');
            }

            $table->string('collection')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();

            $table->unique([
                'slugable_type',
                'slugable_id',
                'deleted_at',
            ],'SLUG_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('url.tables.slug'));
    }
};
