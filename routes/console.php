<?php

use App\Enums\SummaryFrequencyEnum;
use App\Jobs\CreateSummaryJob;
use App\Models\Chat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

// Ежедневные
Artisan::command('create_daily_summers', static function (): void {
    Chat::where('is_allowed_summary', TRUE)->where('summary_frequency', SummaryFrequencyEnum::Daily)
        ->where(static function (Builder $query): void {
            $query->whereNull('summary_created_at')->orWhere('summary_created_at', '<=', now()->subDay()->addHour());
        })->get()->each(function (Chat $chat): void {
            CreateSummaryJob::dispatch($chat);
        });
})->purpose('Запуск ежедневных генерации саммари')->dailyAt('20:00');

// Еженедельные
Artisan::command('create_weekly_summers', static function (): void {
    Chat::where('is_allowed_summary', TRUE)->where('summary_frequency', SummaryFrequencyEnum::Weekly)
        ->where(static function (Builder $query): void {
            $query->whereNull('summary_created_at')->orWhere('summary_created_at', '<=', now()->subWeek()->addHour());
        })->get()->each(function (Chat $chat): void {
            CreateSummaryJob::dispatch($chat);
        });
})->purpose('Запуск еженедельных генерации саммари')->weeklyOn(5, '20:00');
