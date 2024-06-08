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
        Schema::create(config('slug.tables.url'), function (Blueprint $table) {
            $table->id();

            $table->morphs('urlable');
            $table->string('url', 1024)->index();

            $table->timestamps();

            $table->unique([
                'urlable_type',
                'urlable_id',
                'url'
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
        Schema::dropIfExists(config('slug.tables.url'));

        cache()->forget('url');
    }
};
