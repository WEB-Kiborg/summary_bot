<?php

use Illuminate\Support\Facades\Route;

Route::post('telegram-bot-webhook', [\App\Http\Controllers\TelegramController::class, 'webhookHandler']);
