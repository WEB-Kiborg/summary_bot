<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Http\Controllers\TelegramController;
use SergiX44\Nutgram\Telegram\Properties\MessageType;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

$bot->onMessage([TelegramController::class, 'messageHandler']);
