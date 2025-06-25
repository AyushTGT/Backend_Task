<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(){
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_logout')->nullable();
            $table->integer('total_duration_loggedin')->nullable()->comment('In hours');
            $table->string('deleted_by')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login', 'last_logout', 'total_duration_loggedin', 'deleted_by']);
        });
    }
};
