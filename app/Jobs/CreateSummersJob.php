<?php

namespace App\Jobs;

use App\Enums\SummaryFrequencyEnum;
use App\Models\Chat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class CreateSummersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (now()->hour < 20) {
            return;
        }

        $chatsQuery = Chat::where('is_allowed_summary', TRUE)->select('id');

        // Ежедневные
        $chatsQuery->clone()->where('summary_frequency', SummaryFrequencyEnum::Daily)
            ->where(static function (Builder $query): void {
                $query->whereNull('summary_created_at')->orWhere('summary_created_at', '<', now()->subHours(3));
            })->get()->each(function (Chat $chat): void {
                Artisan::call('app:summary', ['chat' => $chat->id]);
            });

        // Еженедельные, в пятницу
        if (now()->isFriday()) {
            $chatsQuery->clone()->where('summary_frequency', SummaryFrequencyEnum::Weekly)
                ->where(static function (Builder $query): void {
                    $query->whereNull('summary_created_at')->where('summary_created_at', '<', now()->subDays(3));
                })->get()->each(function (Chat $chat): void {
                    Artisan::call('app:summary', ['chat' => $chat->id]);
                });
        }

        // Ежемесячные, в последний день месяца
        if (now()->endOfMonth()->day === now()->day) {
            $chatsQuery->clone()->where('summary_frequency', SummaryFrequencyEnum::Monthly)
                ->where(static function (Builder $query): void {
                    $query->whereNull('summary_created_at')->where('summary_created_at', '<', now()->subDays(3));
                })->get()->each(function (Chat $chat): void {
                    Artisan::call('app:summary', ['chat' => $chat->id]);
                });
        }
    }
}
