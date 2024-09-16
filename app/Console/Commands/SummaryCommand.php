<?php

namespace App\Console\Commands;

use App\Jobs\CreateSummaryJob;
use App\Models\Chat;
use Illuminate\Console\Command;

class SummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:summary {chat : The id of the chat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создание саммари для чата';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chat = Chat::where('id', $this->argument('chat'))->firstOrFail();

        CreateSummaryJob::dispatchSync($chat);

        return self::SUCCESS;
    }
}
