<?php

namespace LibreCodeCoop\LSTelegramNotify\Telegram;

use Telegram\Bot\Api;

final class TelegramTestMessageSender
{
    public const MESSAGE = "LSTelegramNotify test message\n\nTelegram configuration is working correctly.";

    public function send(Api $telegram, string $chatId): void
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => self::MESSAGE,
        ]);
    }
}
