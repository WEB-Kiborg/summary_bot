<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Message\Message as NutgramMessage;
use SergiX44\Nutgram\Telegram\Types\Message\MessageOriginUser;

class TelegramController extends Controller
{
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

        if (is_null($message = $bot->message()) || empty($message->getText())) {
            Log::debug('Сообщение не получено', compact('message'));
            throw new \RuntimeException('Сообщение не получено');
        }

        if (is_null($message->from)) {
            Log::debug('Отправитель сообщения не получен', compact('message'));
            throw new \RuntimeException('Отправитель сообщения не получен');
        }

        if ($message->from->is_bot) {
            Log::debug('Получено сообщение от бота, пропускаю', compact('message'));
            return;
        }

        if ($message->chat->type !== ChatType::GROUP && $message->chat->type !== ChatType::SUPERGROUP) {
            throw new \RuntimeException('Получено сообщение не из группы / супергруппы', compact('message'));
        }

        DB::beginTransaction();
        try {
            $chat = Chat::updateOrCreate(['remote_id' => $message->chat->id], ['name' => $message->chat->title ?? '']);

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
