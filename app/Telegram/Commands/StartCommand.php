<?php

namespace App\Telegram\Commands;

use App\Models\User;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class StartCommand extends Command
{
    protected string $command = 'start';

    protected ?string $description = 'Как работать с ботом';

    public function handle(Nutgram $bot): void
    {
        // Register user
        User::updateOrCreate([
            'remote_id' => $bot->userId(),
        ], [
            'first_name' => $bot->user()->first_name,
            'last_name' => $bot->user()->last_name,
        ]);

        $message = "*Начало работы с ботом:*\n";
        $message .= "Для начала работы нужно добавить бота в группу.\n";
        $message .= "После этого вам станут доступны настройки бота для данной группы в разделе Настроек /settings.\n\n";

        $message .= "*Правила генерации саммари:*\n";
        $message .= "1️⃣ Ежедневно: в 16:00 по Мск (рекомендуется для чатов с большим количеством сообщений).\n";
        $message .= "2️⃣ Еженедельно: в пятницу в 16:00 по Мск.\n";
        $message .= "3️⃣ Ежемесячно: в последний день месяца 16:00 по Мск (подойдёт для чатов с небольшим количеством сообщений).\n\n";

        $message .= "*Ограничения:*\n";
        $message .= "Бот предоставляется бесплатно и имеет некоторые ограничения.\n";
        $message .= "1. Общий размер текста сообщений, отправляемый для генерации саммари, не должен превышать 50000 символов (около 8500 слов). ";
        $message .= "Данное ограничение обусловлено лимитами GPT. ";
        $message .= "Выбирайте частоту генерации саммари в зависимости от объёма сообщений в вашем чате.\n\n";

        $message .= "*Поддержка:*\n";
        $message .= "Бот разработан, в первую очередь, для внутреннего использования [компанией ВЕБ-Киборг](https://web-kiborg.ru) и предоставляется \"как есть\". ";
        $message .= "Но вы всегда можете написать нам о технических проблемах или идеях по улучшению в [Телеграм-бот](https://t.me/web_kiborg_bot) или на почту support@web-kiborg.ru.";

        $bot->sendMessage($message, parse_mode: ParseMode::MARKDOWN_LEGACY);
    }
}
