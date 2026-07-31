<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once 'LSTelegramNotifyTest/LSTelegramNotifyTestDouble.php';
require_once 'LSTelegramNotifyTest/Telegram/Bot/Api.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LSTelegramNotifyTest extends TestCase
{
    protected function setUp(): void
    {
        \Survey::$findByPkHandler = null;
        \AppRuntimeMock::$createAbsoluteUrlHandler = null;
    }

    #[DataProvider('sendMessageProvider')]
    public function testSendMessageBehavior(
        array $settings,
        string $title,
        int $surveyId,
        int $responseId,
        ?callable $urlHandler,
        int $expectedRequestCount,
        ?array $expectedPayload,
    ): void
    {
        $plugin = (new \ReflectionClass(LSTelegramNotifyTestDouble::class))->newInstanceWithoutConstructor();
        $plugin->setMockedSettings($settings);

        \AppRuntimeMock::$createAbsoluteUrlHandler = $urlHandler;

        $requests = [];
        $captureRequest = static function (array $params) use (&$requests): array {
            $requests[] = ['sendMessage', $params, null];

            return ['ok' => true];
        };

        $telegram = new Telegram\Bot\Api($captureRequest);

        $plugin->sendMessage($surveyId, $responseId, 'chat-99', $telegram, $title);

        $this->assertCount($expectedRequestCount, $requests);

        if ($expectedPayload === null) {
            return;
        }

        $this->assertSame($expectedPayload['method'], $requests[0][0]);
        $this->assertSame($expectedPayload['chat_id'], $requests[0][1]['chat_id']);
        $this->assertSame($expectedPayload['parse_mode'], $requests[0][1]['parse_mode']);
        $this->assertSame($expectedPayload['text'], $requests[0][1]['text']);
    }

    public static function sendMessageProvider(): array
    {
        return [
            'send disabled skips telegram request' => [
                [
                    'SendMessage' => false,
                ],
                'Any title',
                101,
                202,
                null,
                0,
                null,
            ],
            'send enabled builds payload with placeholders' => [
                [
                    'SendMessage' => true,
                    'DefaultText' => 'Survey={surveyId};Response={responseId};Title={title};Pdf={urlPDF}',
                    'ParseMode' => 'MarkdownV2',
                ],
                'Customer Satisfaction',
                11,
                22,
                static function ($route, array $params): string {
                    return 'https://example.test' . $route . '?surveyid=' . $params['surveyid'] . '&id=' . $params['id'];
                },
                1,
                [
                    'method' => 'sendMessage',
                    'chat_id' => 'chat-99',
                    'parse_mode' => 'MarkdownV2',
                    'text' => 'Survey=11;Response=22;Title=Customer Satisfaction;Pdf=https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=11&id=22',
                ],
            ],
        ];
    }
}
