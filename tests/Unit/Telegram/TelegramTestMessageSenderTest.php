<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Support/Telegram/Bot/Api.php';

use LibreCodeCoop\LSTelegramNotify\Telegram\TelegramTestMessageSender;
use PHPUnit\Framework\TestCase;
use Telegram\Bot\Api;

final class TelegramTestMessageSenderTest extends TestCase
{
    public function testSendUsesPlainTelegramMessageWithFixedContent(): void
    {
        $requests = [];
        $telegram = new Api(static function (array $params) use (&$requests): array {
            $requests[] = $params;

            return ['ok' => true];
        });

        (new TelegramTestMessageSender())->send($telegram, 'chat-42');

        $this->assertSame([
            [
                'chat_id' => 'chat-42',
                'text' => TelegramTestMessageSender::MESSAGE,
            ],
        ], $requests);
    }
}
