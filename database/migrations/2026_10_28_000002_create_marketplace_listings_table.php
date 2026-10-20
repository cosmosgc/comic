<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A classified listing. Price is a reference/asking value only —
     * the site never moves money or hosts checkout.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_listings')) {
            Schema::create('marketplace_listings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('marketplace_category_id')->constrained('marketplace_categories')->restrictOnDelete();
                $table->string('kind', 20)->default('physical');
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description');
                $table->string('condition', 20)->default('na');
                $table->string('listing_type', 20)->default('sale');
                $table->decimal('price', 10, 2)->nullable();
                $table->char('currency', 3)->default('BRL');
                $table->boolean('price_negotiable')->default(false);
                $table->unsignedInteger('quantity')->default(1);
                $table->string('location_city')->nullable();
                $table->string('location_region')->nullable();
                $table->string('location_country')->nullable();
                $table->string('shipping', 20)->default('arrange');
                $table->string('status', 20)->default('draft');
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_approved')->default(true);
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('watch_count')->default(0);
                $table->unsignedInteger('inquiry_count')->default(0);
                $table->string('contact_mode', 20)->default('in_app');
                $table->string('contact_email')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('bumped_at')->nullable();
                $table->timestamp('sold_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'published_at']);
                $table->index(['marketplace_category_id', 'status']);
                $table->index(['user_id', 'status']);
                $table->index('kind');
                $table->index('price');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
