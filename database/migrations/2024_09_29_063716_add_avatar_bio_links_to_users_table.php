<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAvatarBioLinksToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_image_path')->nullable(); // For storing avatar image path
            $table->text('bio')->nullable(); // For storing user biography
            $table->json('links')->nullable(); // For storing an array of links
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_image_path', 'bio', 'links']);
        });
    }
}
