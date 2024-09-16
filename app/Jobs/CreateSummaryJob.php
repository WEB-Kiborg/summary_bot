<?php

namespace App\Jobs;

use App\Exceptions\RetryException;
use App\Formatters\GptResponseFormatter;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class CreateSummaryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Chat $chat) { }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->chat->id))->dontRelease()];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $prompt = "Я приведу тебе историю переписки нескольких людей. ";
        $prompt .= "Создай саммари с выделением всех ключевых договорённостей за каждый день. ";

        $prompt .= "Структура сообщения:\n";
        $prompt .= "Саммари (заголовок уровня 1)\n";
        $prompt .= "Дата (заголовок уровня 2)\nСаммари дня\n\n";
        $prompt .= "Дата (заголовок уровня 2)\nСаммари дня\n\n";
        $prompt .= "и так по каждому дню...\n";
        $prompt .= "Общие итоги (заголовок уровня 1)\n";
        $prompt .= "Общие ключевые итоги по всем дням...\n\n";

        $prompt .= "Добавляй в саммари имена пользователей и даты, если это необходимо. ";
        $prompt .= "Если были какие-то договорённости по срокам – укажи их; если сроки были пропущены или отложены – укажи это. ";
        $prompt .= "Сообщение должно быть отформатировано так, чтобы человеку было удобно его читать. ";
        $prompt .= "В ответе верни только результат, без лишнего текста.\n\n";
        $prompt .= "Сообщения:\n";

        $messages = Message::where('chat_id', $this->chat->id)->orderBy('created_at');
        if (!is_null($this->chat->summary_created_at)) {
            $messages->where('created_at', '>=', $this->chat->summary_created_at);
        }
        $messages = $messages->get();

        if ($messages->isEmpty()) {
            Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Нет сообщений для создания саммари");
            $this->chat->update(['summary_created_at' => now()]);
            return;
        }

        $promptMessages = [];
        foreach ($messages as $message) {
            $promptMessages[] = "$message->first_name $message->last_name {$message->created_at->format('d.m.Y H:i')}\n$message->message";
        }

        // Разделим промпт на куски, чтобы отправить его несколькими сообщениями и не упереться в ограничение GPT
        $index = 0;
        $prompt = [$index => $prompt];
        foreach ($promptMessages as $promptMessage) {
            if (str($prompt[$index])->length() >= 10000) {
                $index++;
            }

            $prompt[$index] .= "$promptMessage\n";
        }
        unset($promptMessages);

        if (count($prompt) > 5) {
            Log::error("[SummaryCommand -> handle] (Chat {$this->chat->id}) Длина сообщений составила " . (count($prompt) * 10000) . ' символов');
            $this->chat->update(['summary_created_at' => now()]);
            return;
        }

        DB::beginTransaction();
        try {
            Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Создаю чат в Neyro");
            $http = Http::retry(1, 60 * 1000)->timeout(config('services.neyro.timeout'))
                ->connectTimeout(config('services.neyro.timeout'))->asJson()->acceptJson()
                ->baseUrl(config('services.neyro.base_url'))->withToken(config('services.neyro.token'));

            $neyroChatRequest = $http->post('chats', [
                'name' => Str::limit("Cаммари чата {$this->chat->name} от " . now()->format('d.m.Y H:i'), 255, ''),
                'model' => 'chatgpt',
                'version' => 'gpt-4o',
                'is_default_name' => FALSE,
            ]);

            if (!$neyroChatRequest->successful() || empty($neyroChat = $neyroChatRequest->json()['data'] ?? []) || empty($neyroChat['id'])) {
                Log::error("[SummaryCommand -> handle] (Chat {$this->chat->id}) Не удалось создать чат в Neyro", compact('neyroChatRequest'));
                throw new \RuntimeException('Не удалось создать чат в Neyro');
            }

            Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Отправляю сообщения в Neyro");

            $iteration = 1;
            foreach ($prompt as $promptItem) {
                $neyroStoreMessageRequest = $http->post("chats/{$neyroChat['id']}/messages", [
                    'message' => $promptItem,
                    // Отдаём команду отправлять в GPT только после отправки последнего
                    'send_to_ai' => $iteration === count($prompt),
                ]);

                if (!$neyroStoreMessageRequest->successful()) {
                    Log::error("[SummaryCommand -> handle] (Chat {$this->chat->id}) Не удалось отправить сообщение в Neyro", compact('neyroStoreMessageRequest'));
                    throw new \RuntimeException('Не удалось отправить сообщение в Neyro');
                }

                $iteration++;
            }


            Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Ожидаю ответное сообщение от Neyro");
            sleep(20);
            $result = retry(10, function () use ($neyroChat, $http): string {
                $neyroGetMessagesRequest = $http->get("chats/{$neyroChat['id']}/messages");
                if (!$neyroGetMessagesRequest->successful()) {
                    throw new RetryException("Неуспешный ответ от Neyro", $neyroGetMessagesRequest->getStatusCode());
                }

                $neyroGetMessagesResponse = $neyroGetMessagesRequest->json()['data'][0] ?? [];

                if (!empty($neyroGetMessagesResponse['message']) && $neyroGetMessagesResponse['role'] === 'assistant') {
                    return $neyroGetMessagesResponse['message'];
                }

                Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Повторная попытка ожидания ответного сообщения от Neyro");
                throw new RetryException;
            }, static function (int $attempt): int {
                return $attempt * 5 * 1000;
            }, static function (\Throwable $e): bool {
                return $e instanceof RetryException;
            });

            $http->delete("chats/{$neyroChat['id']}");

            if (!empty($result)) {
                $this->chat->summaries()->create(['summary' => $result]);

                $result = GptResponseFormatter::formatText($result);
                $bot = app()->make(Nutgram::class);
                $bot->sendMessage($result, $this->chat->remote_id, parse_mode: ParseMode::MARKDOWN_LEGACY);

                $this->chat->update(['summary_created_at' => now()]);
            }
            DB::commit();
        } catch (TelegramException $e) {
            DB::rollBack();
            Log::error("[SummaryCommand -> handle] (Chat {$this->chat->id}) Произошла ошибка при отправке сообщения в Telegram", compact('e'));

            $this->fail($e);
            return;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[SummaryCommand -> handle] (Chat {$this->chat->id}) Произошла ошибка при создании саммари", compact('e'));

            $this->fail($e);
            return;
        }

        Log::debug("[SummaryCommand -> handle] (Chat {$this->chat->id}) Создание саммари успешно завершено");
    }
}
