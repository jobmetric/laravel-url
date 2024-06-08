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
            $table->string('url', config('url.url_long'))->unique()->index();

            $table->string('collection')->nullable()->index();

            $table->timestamps();

            $table->unique([
                'urlable_type',
                'urlable_id',
                'collection'
            ], 'URL_UNIQUE');
        });

        cache()->forget('url');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('url.tables.url'));

        cache()->forget('url');
    }
};
