<?php

use App\Enums\SummaryFrequencyEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('remote_id')->unique();
            $table->string('name');
            $table->boolean('is_allowed_summary')->default(FALSE);
            $table->enum('summary_frequency', [
                SummaryFrequencyEnum::Daily->value,
                SummaryFrequencyEnum::Weekly->value,
                SummaryFrequencyEnum::Monthly->value,
            ]);
            $table->dateTime('summary_created_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
