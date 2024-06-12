<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('index')->index();
            $table->string('field')->index();
            $table->text('content');

            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText('content');
            }

            $table->index(["key", "index"]);
            $table->index(["key", "index", "field"]);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('search_index');
    }
};
