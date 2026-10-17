<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User reports on posts. Reviewed through the admin queue; the
     * subject is a post today, with room to grow (comment_id etc.) later.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
                $table->string('reason', 40)->default('other');
                $table->text('details')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['reporter_id', 'post_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
