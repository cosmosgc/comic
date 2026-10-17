<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Stable @handle for mentions and clean profile URLs. Nullable so the
     * column can be added on a live site, then backfilled from the name.
     * Uniqueness is enforced with a counter suffix on collisions.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'handle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('handle', 30)->nullable()->unique()->after('name');
            });
        }

        $taken = [];
        DB::table('users')
            ->whereNull('handle')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($user) use (&$taken) {
                $base = Str::slug((string) $user->name, '');
                $base = $base !== '' ? Str::lower($base) : 'user'.$user->id;
                $base = Str::limit($base, 24, '');

                $handle = $base;
                $n = 1;
                while (in_array($handle, $taken, true)
                    || DB::table('users')->where('handle', $handle)->where('id', '!=', $user->id)->exists()) {
                    $handle = $base.$n;
                    $n++;
                }

                $taken[] = $handle;
                DB::table('users')->where('id', $user->id)->update(['handle' => $handle]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'handle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('handle');
            });
        }
    }
};
