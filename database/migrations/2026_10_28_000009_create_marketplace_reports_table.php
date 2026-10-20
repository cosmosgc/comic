<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_reports')) {
            Schema::create('marketplace_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('marketplace_listing_id')->nullable()->constrained('marketplace_listings')->cascadeOnDelete();
                $table->unsignedBigInteger('marketplace_message_id')->nullable();
                $table->string('reason', 30);
                $table->text('details')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('marketplace_message_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reports');
    }
};
