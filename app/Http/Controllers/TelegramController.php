<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Types\Message\Message as NutgramMessage;
use SergiX44\Nutgram\Telegram\Types\Message\MessageOriginUser;

class TelegramController extends Controller
{
    /**
     * Обработчик вебхуков от Telegram
     * @param \SergiX44\Nutgram\Nutgram $bot
     * @return void
     */
    public function webhookHandler(Nutgram $bot): void
    {
        try {
            $bot->setRunningMode(Webhook::class);
            $bot->run();
        } catch (\Throwable $e) {
            Log::error('Не удалось обработать webhook от Telegram!', compact('e'));
        }
    }

    public function messageHandler(Nutgram $bot): void
    {
        if (!$bot->update()?->getType()?->isMessageType()) {
            Log::debug('Неподходящий тип обновления', compact('bot'));
            return;
        }

        if (is_null($message = $bot->message())) {
            Log::error('Сообщение не получено', compact('message'));
            return;
        }

        if (!in_array($message->chat->type, [ChatType::GROUP, ChatType::SUPERGROUP, ChatType::PRIVATE], TRUE)) {
            Log::debug('Неверный тип чата', compact('message'));
            return;
        }

        if (is_null($message->from)) {
            Log::error('Отправитель сообщения не получен', compact('message'));
            return;
        }

        if ($message->from->is_bot) {
            Log::debug('Получено сообщение от бота, пропускаю', compact('message'));
            return;
        }

        if ($message->chat->type === ChatType::GROUP || $message->chat->type === ChatType::SUPERGROUP) {
            $this->groupMessagesHandler($bot, $message);
        } elseif ($message->chat->type === ChatType::PRIVATE) {
            $this->personalMessagesHandler($bot, $message);
        }
    }

    /**
     * Обработчик персональных сообщений к боту
     * @param \SergiX44\Nutgram\Nutgram $bot
     * @param \SergiX44\Nutgram\Telegram\Types\Message\Message $message
     * @return void
     */
    private function personalMessagesHandler(Nutgram $bot, NutgramMessage $message): void
    {
        $bot->sendMessage('Бот не обрабатывает входящие личные сообщения.');
    }

    /**
     * Обработчик сообщений в группе
     * @param \SergiX44\Nutgram\Nutgram $bot
     * @param \SergiX44\Nutgram\Telegram\Types\Message\Message $message
     * @return void
     */
    private function groupMessagesHandler(Nutgram $bot, NutgramMessage $message): void
    {
        $chatName = $message->chat->title ?? "{$message->chat->type->name} {$message->chat->id}";

        // 1. Проверяем не добавили ли бота в группу
        if ($message->getType() === MessageType::NEW_CHAT_MEMBERS) {
            $botId = explode(':', config('nutgram.token'))[0] ?? '';

            foreach ($message->new_chat_members as $newMember) {
                if ($newMember->id === (int)$botId) {
                    $user = User::updateOrCreate([
                        'remote_id' => $message->from->id,
                    ], [
                        'first_name' => $message->from->first_name,
                        'last_name' => $message->from->last_name,
                    ]);

                    Chat::updateOrCreate(['remote_id' => $message->chat->id], [
                        'name' => $chatName,
                        'admin_id' => $user->id,
                    ]);

                    $messageToSend = "Привет! 👋 \n";
                    $messageToSend .= "Я – бот. Буду собирать сообщения из этого чата и делать по ним саммари с помощью ИИ.";
                    $bot->sendMessage($messageToSend);
                    return;
                }
            }
        }

        // 2. Проверяем не удалили ли бота из группы
        if ($message->getType() === MessageType::LEFT_CHAT_MEMBER && $message->left_chat_member->id === (int)explode(':', config('nutgram.token'))[0]) {
            $chat = Chat::where('remote_id', $message->chat->id)->first();
            if (is_null($chat)) {
                Log::error('Ошибка идентификации чата');
                return;
            }

            $chat->summaries()->delete();
            $chat->messages()->delete();
            $chat->delete();

            return;
        }

        if (empty($message->getText())) {
            Log::debug('Текст сообщения не получен', compact('message'));
            return;
        }

        DB::beginTransaction();
        try {
            $chat = Chat::updateOrCreate(['remote_id' => $message->chat->id], ['name' => $chatName]);

            if ($chat->messages()->where('remote_id', $message->message_id)->exists()) {
                $this->updateMessage($chat, $message);
            } else {
                $this->storeMessage($chat, $message);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Не удалось сохранить сообщение из Телеграм', compact('e'));
        }
    }

    private function storeMessage(Chat $chat, NutgramMessage $message): void
    {
        if ($message->forward_origin instanceof MessageOriginUser) {
            $from = $message->forward_origin->sender_user;
            $created = $message->forward_origin->date;
        } else {
            $from = $message->from;
            $created = $message->date;
        }
        $created = Carbon::createFromTimestamp($created)->timezone(config('app.timezone'))->toDate();

        $chat->messages()->create([
            'first_name' => $from->first_name ?? '',
            'last_name' => $from->last_name ?? '',
            'username' => $from->username ?? '',
            'message' => $message->getText(),
            'remote_id' => $message->message_id,
            'created_at' => $created,
            'updated_at' => $created,
        ]);
    }

    private function updateMessage(Chat $chat, NutgramMessage $message): void
    {
        $chat->messages()->where('remote_id', $message->message_id)->update([
            'message' => $message->getText(),
            'updated_at' => Carbon::createFromTimestamp($message->edit_date)->timezone(config('app.timezone'))
                ->toDate(),
        ]);
    }
}
