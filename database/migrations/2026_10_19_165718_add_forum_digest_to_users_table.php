<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'forum_digest')) {
            Schema::table('users', function (Blueprint $table) {
                // Opt-in weekly forum digest (see forum:digest).
                $table->boolean('forum_digest')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'forum_digest')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('forum_digest');
            });
        }
    }
};
