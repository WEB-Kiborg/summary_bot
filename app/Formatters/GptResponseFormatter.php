<?php

namespace App\Formatters;

class GptResponseFormatter
{
    /**
     * Форматирует текст для Telegram Markdown, удаляя не поддерживаемые теги.
     *
     * @param string $text Текст для форматирования.
     * @return string Отформатированный текст.
     */
    public static function formatText(string $text): string
    {
        //$text = preg_replace("/\*(.+)\*/um", "_$1_", $text); // Italic
        $text = preg_replace("/\*\*(.+)\*\*/um", "*$1*", $text); // Bold

        // Заголовки 1-6
        $text = preg_replace("/^# (.+)/um", "📌 *$1*", $text);
        $text = preg_replace("/^## (.+)/um", "✏️ *$1*", $text);
        $text = preg_replace("/^### (.+)/um", "📚 *$1*", $text);
        $text = preg_replace("/^#### (.+)/um", "🔖 *$1*", $text);
        $text = preg_replace("/^##### (.+)/um", "🔖 *$1*", $text);
        $text = preg_replace("/^###### (.+)/um", "🔖 *$1*", $text);

        return $text;
    }
}
