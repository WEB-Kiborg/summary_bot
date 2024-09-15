<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email', 'name', 'email_verified_at', 'password', 'remember_token');

            $table->bigInteger('remote_id')->after('id');
            $table->string('first_name')->after('remote_id')->nullable();
            $table->string('last_name')->after('first_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remote_id', 'first_name', 'last_name');

            $table->string('name')->after('id');
            $table->string('email')->after('name')->unique();
            $table->timestamp('email_verified_at')->after('email')->nullable();
            $table->string('password')->after('email_verified_at');
            $table->string('remember_token', 100)->after('password')->nullable();
        });
    }
};
