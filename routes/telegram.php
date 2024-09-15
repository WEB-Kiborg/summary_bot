<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Http\Controllers\TelegramController;
use App\Telegram\Commands\StartCommand;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/
$bot->registerCommand(StartCommand::class);
$bot->onCommand('settings', static fn(Nutgram $bot) => \App\Telegram\Conversations\SettingsConversation::begin($bot))->description('Настройки генерации саммари');

$bot->onMessage([TelegramController::class, 'messageHandler']);
$bot->onEditedMessage([TelegramController::class, 'messageHandler']);

// Регистрация команд выключена, тк иначе команды отображаются в группах
//$bot->registerMyCommands();
