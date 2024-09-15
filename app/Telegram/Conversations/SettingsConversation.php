<?php

namespace App\Telegram\Conversations;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Conversations\InlineMenu;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

class SettingsConversation extends InlineMenu
{
    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function start(Nutgram $bot): void
    {
        $bot->deleteUserData('chat_id');

        $chats = Chat::whereHas('admin', static function (Builder $query) use ($bot): void {
            $query->where('remote_id', $bot->userId());
        })->orderBy('name')->get();

        if ($chats->isEmpty()) {
            $bot->sendMessage('Нет чатов под вашим управлением.');
            $this->end();
            return;
        }

        $this->clearButtons()->menuText('Выберите чат:');
        foreach ($chats as $chat) {
            $this->addButtonRow(InlineKeyboardButton::make($chat->name, callback_data: "$chat->id@handleChat"));
        }

        $this->showMenu();
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handleChat(Nutgram $bot): void
    {
        $chatId = empty($bot->callbackQuery()->data) ? $bot->getUserData('chat_id') : $bot->callbackQuery()->data;
        if (empty($chat = Chat::find($chatId))) {
            Log::error('[SettingsConversation -> handleChat] Ошибка идентификации чата', [
                'data_chat_id' => $bot->callbackQuery()->data,
                'bot_chat_id' => $bot->getUserData('chat_id'),
            ]);
            $this->error($bot);
            return;
        }

        $bot->setUserData('chat_id', $chatId);

        $message = "*Текущие настройки чата $chat->name:*\n";
        $message .= ('Генерация саммари: ' . ($chat->is_allowed_summary ? 'да' : 'нет') . "\n");
        $message .= "Частота генерации: {$chat->summary_frequency->forHuman()}";

        $this->clearButtons()->menuText($message, ['parse_mode' => ParseMode::MARKDOWN_LEGACY]);
        $this->addButtonRow(InlineKeyboardButton::make('📅 Изменить частоту генерации', callback_data: "0@handleFrequency"));

        if ($chat->is_allowed_summary) {
            $this->addButtonRow(InlineKeyboardButton::make('🚫 Выключить генерацию', callback_data: "0@handleAllowed"));
        } else {
            $this->addButtonRow(InlineKeyboardButton::make('✅ Включить генерацию', callback_data: "1@handleAllowed"));
        }

        $this->addButtonRow(InlineKeyboardButton::make('🔙 Вернуться', callback_data: "@start"));
        $this->showMenu();
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handleFrequency(Nutgram $bot): void
    {
        if (empty($chatId = $bot->getUserData('chat_id')) || empty($chat = Chat::find($chatId))) {
            Log::error('[SettingsConversation -> handleFrequency] Ошибка идентификации чата', ['bot_chat_id' => $bot->getUserData('chat_id')]);
            $this->error($bot);
            return;
        }

        if (!empty($value = $bot->callbackQuery()->data)) {
            $chat->update(['summary_frequency' => $value]);
            $bot->callbackQuery()->data = NULL;
            $this->handleChat($bot);
            return;
        }

        $message = "*Правила генерации саммари:*\n";
        $message .= "1️⃣ Ежедневно: в 16:00 по Мск (рекомендуется для чатов с большим количеством сообщений).\n";
        $message .= "2️⃣ Еженедельно: в пятницу в 16:00 по Мск.\n";
        $message .= "3️⃣ Ежемесячно: в последний день месяца 16:00 по Мск (подойдёт для чатов с небольшим количеством сообщений).";

        $this->clearButtons()->menuText($message, ['parse_mode' => ParseMode::MARKDOWN_LEGACY])
            ->addButtonRow(InlineKeyboardButton::make('1️⃣ Ежедневно', callback_data: "daily@handleFrequency"))
            ->addButtonRow(InlineKeyboardButton::make('2️⃣ Еженедельно', callback_data: "weekly@handleFrequency"))
            ->addButtonRow(InlineKeyboardButton::make('3️⃣ Ежемесячно', callback_data: "monthly@handleFrequency"))
            ->showMenu();
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handleAllowed(Nutgram $bot): void
    {
        if (empty($chatId = $bot->getUserData('chat_id')) || empty($chat = Chat::find($chatId))) {
            Log::error('[SettingsConversation -> handleAllowed] Ошибка идентификации чата', ['bot_chat_id' => $bot->getUserData('chat_id')]);
            $this->error($bot);
            return;
        }

        $chat->update(['is_allowed_summary' => !empty($bot->callbackQuery()->data)]);
        $bot->callbackQuery()->data = NULL;

        $this->handleChat($bot);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function error(Nutgram $bot): void
    {
        $this->closeMenu('Произошла ошибка');
        $this->end();
    }
}
