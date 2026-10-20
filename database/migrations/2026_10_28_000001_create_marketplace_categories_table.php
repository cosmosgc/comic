<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketplace sections/categories. Two levels in one self-referential
     * table, like the forum: a null parent_id is a section.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_categories')) {
            Schema::create('marketplace_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('marketplace_categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->string('color')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->unsignedInteger('listing_count')->default(0);
                $table->timestamps();

                $table->index(['parent_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_categories');
    }
};
