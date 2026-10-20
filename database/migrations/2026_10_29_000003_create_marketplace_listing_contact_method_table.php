<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The contact channels a seller chooses to show on one listing. Hidden
     * profile methods may be selected here; the listing is an explicit
     * disclosure, while the profile keeps its own privacy setting.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_listing_contact_method')) {
            Schema::create('marketplace_listing_contact_method', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->unsignedBigInteger('user_contact_method_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('marketplace_listing_id', 'mlcm_listing_fk')
                    ->references('id')->on('marketplace_listings')->cascadeOnDelete();
                $table->foreign('user_contact_method_id', 'mlcm_contact_fk')
                    ->references('id')->on('user_contact_methods')->cascadeOnDelete();
                $table->unique(['marketplace_listing_id', 'user_contact_method_id'], 'mlcm_listing_contact_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_contact_method');
    }
};
