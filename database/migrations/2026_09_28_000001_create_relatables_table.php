<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tableName(), function (Blueprint $table) {
            $table->id();
            $table->morphs('relatable');
            $table->morphs('related');
            $table->float('score')->default(1);
            $table->float('boost')->default(1);
            $table->jsonb('options')->nullable();
            $table->timestamps();
            $table->unique(['relatable_type', 'relatable_id', 'related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    protected function tableName(): string
    {
        $model = Relatable::modelClass();

        return (new $model)->getTable();
    }
};
