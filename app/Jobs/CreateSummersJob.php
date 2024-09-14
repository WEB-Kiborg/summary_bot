<?php

namespace App\Jobs;

use App\Enums\SummaryFrequencyEnum;
use App\Models\Chat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class CreateSummersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach (Chat::where('is_allowed_summary', TRUE)->get() as $chat) {
            if ((empty($chat->summary_frequency) || $chat->summary_frequency === SummaryFrequencyEnum::Weekly) && now()->diffInDays($chat->summary_created_at) >= 7) {
                Artisan::call('app:summary', ['chat' => $chat->id]);
            } elseif ($chat->summary_frequency === SummaryFrequencyEnum::Monthly && now()->diffInMonths($chat->summary_created_at) >= 1) {
                Artisan::call('app:summary', ['chat' => $chat->id]);
            } elseif ($chat->summary_frequency === SummaryFrequencyEnum::Daily && now()->diffInDays($chat->summary_created_at) >= 1) {
                Artisan::call('app:summary', ['chat' => $chat->id]);
            }
        }
    }
}
