<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatables', function (Blueprint $table) {
            $table->id();
            $table->morphs('relatable');
            $table->morphs('related');
            $table->float('score')->default(1);
            $table->float('boost')->default(1);
            $table->jsonb('options')->nullable();
            $table->timestamps();
            // The generated name is 69 characters, longer than MySQL allows.
            $table->unique(['relatable_type', 'relatable_id', 'related_type', 'related_id'], 'relatables_relation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatables');
    }
};
