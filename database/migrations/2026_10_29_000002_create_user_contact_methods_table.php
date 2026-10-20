<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user can keep several contact channels. `is_public` controls the
     * public profile only; a listing can expose a hidden method when the
     * seller explicitly selects it.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_contact_methods')) {
            Schema::create('user_contact_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);
                $table->string('label', 60)->nullable();
                $table->string('value', 190);
                $table->boolean('is_public')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contact_methods');
    }
};
