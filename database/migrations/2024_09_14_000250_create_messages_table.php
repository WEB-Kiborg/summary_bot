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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('remote_id');
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username');
            $table->longText('message');
            $table->timestamps();

            $table->unique(['chat_id', 'remote_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
