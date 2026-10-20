<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_messages')) {
            Schema::create('marketplace_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_conversation_id')->constrained('marketplace_conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['marketplace_conversation_id', 'created_at'], 'marketplace_messages_conversation_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_messages');
    }
};
