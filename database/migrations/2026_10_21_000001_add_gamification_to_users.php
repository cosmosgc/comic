<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gamification profile fields (experience, level, activity
     * streaks) plus the earned-badges table. Everything is additive:
     * hosts that lag behind simply keep the defaults.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('experience')->default(0)->after('admin_level');
            $table->unsignedInteger('level')->default(1)->after('experience');
            $table->date('last_active_on')->nullable()->after('level');
            $table->unsignedInteger('current_streak')->default(0)->after('last_active_on');
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
            $table->index('level');
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 50);
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropColumn([
                'experience',
                'level',
                'last_active_on',
                'current_streak',
                'longest_streak',
            ]);
        });
    }
};
