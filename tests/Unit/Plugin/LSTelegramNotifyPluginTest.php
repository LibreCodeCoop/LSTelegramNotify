<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Support/Doubles/Plugin/LSTelegramNotifyPluginDouble.php';
require_once dirname(__DIR__, 2) . '/Support/Telegram/Bot/Api.php';

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Telegram\Bot\Api;

class LSTelegramNotifyPluginTest extends TestCase
{
    protected function setUp(): void
    {
        \Survey::$findByPkHandler = null;
        \AppRuntimeMock::$createAbsoluteUrlHandler = null;
    }

    public function testInitSubscribesToRequiredPluginEvents(): void
    {
        $plugin = $this->newPlugin();

        $plugin->init();

        $this->assertSame(
            ['newSurveySettings', 'afterSurveyComplete', 'beforeSurveySettings'],
            $plugin->getSubscribedEvents()
        );
    }

    public function testAfterSurveyCompleteSkipsProcessingWhenNotificationsAreDisabled(): void
    {
        $plugin = new class extends LSTelegramNotifyPluginDouble {
            /** @var array<int, array<string, mixed>> */
            public array $sendMessageCalls = [];

            public function sendMessage(int $surveyId, int $responseId, string $chatId, Api $telegram, string $title): void
            {
                $this->sendMessageCalls[] = [
                    'surveyId' => $surveyId,
                    'responseId' => $responseId,
                    'chatId' => $chatId,
                    'title' => $title,
                ];
            }
        };
        $plugin->setMockedSettings([
            'Enable' => false,
        ]);
        $plugin->setMockedEvent($this->newEvent([
            'surveyId' => 55,
            'responseId' => 66,
        ]));

        $plugin->afterSurveyComplete();

        $this->assertSame([], $plugin->sendMessageCalls);
    }

    public function testAfterSurveyCompleteSkipsProcessingWhenSurveyCannotBeLoaded(): void
    {
        $plugin = new class extends LSTelegramNotifyPluginDouble {
            /** @var array<int, array<string, mixed>> */
            public array $sendMessageCalls = [];

            public function sendMessage(int $surveyId, int $responseId, string $chatId, Api $telegram, string $title): void
            {
                $this->sendMessageCalls[] = [
                    'surveyId' => $surveyId,
                    'responseId' => $responseId,
                    'chatId' => $chatId,
                    'title' => $title,
                ];
            }
        };
        $plugin->setMockedSettings([
            'Enable' => true,
        ]);
        $plugin->setMockedEvent($this->newEvent([
            'surveyId' => 55,
            'responseId' => 66,
        ]));

        $plugin->afterSurveyComplete();

        $this->assertSame([], $plugin->sendMessageCalls);
    }

    public function testSendMessageSkipsTelegramWhenDisabled(): void
    {
        $plugin = $this->newPlugin();
        $plugin->setMockedSettings([
            'SendMessage' => false,
        ]);

        $requests = [];
        $telegram = new Api(static function (array $params) use (&$requests): array {
            $requests[] = $params;

            return ['ok' => true];
        });

        $plugin->sendMessage(101, 202, 'chat-99', $telegram, 'Any title');

        $this->assertSame([], $requests);
    }

    public function testSendMessageDelegatesRenderingWithBasePlaceholdersAndFieldValues(): void
    {
        $plugin = $this->newPlugin();
        $plugin->setMockedSettings([
            'SendMessage' => true,
            'DefaultText' => 'Mensagem {{ surveyId }}',
            'ParseMode' => 'MarkdownV2',
        ]);

        $fieldValues = [
            'CONTATO[EMAIL]' => [
                'question' => 'E-mail para contato',
                'answer' => 'ada@example.test',
                'raw' => 'ada@example.test',
            ],
        ];

        $renderer = new class extends MessageTemplateRenderer {
            /** @var array<int, array{template: string, placeholders: array<string, string>, fieldValues: array<string, array<string, string>>}> */
            public array $calls = [];

            public function render(string $template, array $placeholders, array $fieldValues): string
            {
                $this->calls[] = [
                    'template' => $template,
                    'placeholders' => $placeholders,
                    'fieldValues' => $fieldValues,
                ];

                return 'Mensagem renderizada';
            }
        };

        $provider = new class($fieldValues) extends SurveyFieldValueProvider {
            /** @var array<int, array{surveyId: int, responseId: int}> */
            public array $calls = [];

            /** @var array<string, array<string, string>> */
            private array $fieldValues;

            /** @param array<string, array<string, string>> $fieldValues */
            public function __construct(array $fieldValues)
            {
                $this->fieldValues = $fieldValues;
            }

            public function getFieldValues(int $surveyId, int $responseId): array
            {
                $this->calls[] = [
                    'surveyId' => $surveyId,
                    'responseId' => $responseId,
                ];

                return $this->fieldValues;
            }
        };

        $plugin->setMockedMessageTemplateRenderer($renderer);
        $plugin->setMockedSurveyFieldValueProvider($provider);

        \AppRuntimeMock::$createAbsoluteUrlHandler = static function ($route, array $params): string {
            $query = http_build_query($params);

            return 'https://example.test' . $route . ($query !== '' ? '?' . $query : '');
        };

        $requests = [];
        $telegram = new Api(static function (array $params) use (&$requests): array {
            $requests[] = $params;

            return ['ok' => true];
        });

        $plugin->sendMessage(55, 66, 'chat-99', $telegram, 'Ficha de cadastro');

        $this->assertSame([
            [
                'surveyId' => 55,
                'responseId' => 66,
            ],
        ], $provider->calls);
        $this->assertCount(1, $renderer->calls);
        $this->assertSame('Mensagem {{ surveyId }}', $renderer->calls[0]['template']);
        $this->assertSame([
            'surveyId' => '55',
            'responseId' => '66',
            'urlPDF' => 'https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=55&id=66',
            'urlSurvey' => 'https://example.test/surveyAdministration/view?surveyid=55',
            'urlDetails' => 'https://example.test/responses/view?surveyId=55&id=66',
            'urlEdit' => 'https://example.test/admin/dataentry/sa/editdata/subaction/edit/surveyId/55/id/66/browseLang',
            'urlExport' => 'https://example.test/admin/export/sa/exportresults/surveyid/55/id/66',
            'urlAttachments' => 'https://example.test/responses/downloadfiles?surveyId=55&responseIds=66',
            'title' => 'Ficha de cadastro',
        ], $renderer->calls[0]['placeholders']);
        $this->assertSame($fieldValues, $renderer->calls[0]['fieldValues']);
        $this->assertSame([
            [
                'chat_id' => 'chat-99',
                'text' => 'Mensagem renderizada',
                'parse_mode' => 'MarkdownV2',
            ],
        ], $requests);
    }

    public function testSendMessageEscapesTemplateValuesWhenParseModeIsHtml(): void
    {
        $plugin = $this->newPlugin();
        $plugin->setMockedSettings([
            'SendMessage' => true,
            'DefaultText' => 'Mensagem {{ title }}',
            'ParseMode' => 'HTML',
        ]);

        $renderer = new class extends MessageTemplateRenderer {
            /** @var array<int, array{placeholders: array<string, string>, fieldValues: array<string, array<string, string>>}> */
            public array $calls = [];

            public function render(string $template, array $placeholders, array $fieldValues): string
            {
                $this->calls[] = [
                    'placeholders' => $placeholders,
                    'fieldValues' => $fieldValues,
                ];

                return 'Mensagem segura';
            }
        };

        $provider = new class extends SurveyFieldValueProvider {
            public function getFieldValues(int $surveyId, int $responseId): array
            {
                return [
                    'CONTATO[EMAIL]' => [
                        'question' => 'Nome <b>visível</b>',
                        'answer' => 'Ada & Bob',
                        'raw' => '"quoted"',
                    ],
                ];
            }
        };

        $plugin->setMockedMessageTemplateRenderer($renderer);
        $plugin->setMockedSurveyFieldValueProvider($provider);

        $requests = [];
        $telegram = new Api(static function (array $params) use (&$requests): array {
            $requests[] = $params;

            return ['ok' => true];
        });

        $plugin->sendMessage(55, 66, 'chat-99', $telegram, 'Ficha <cadastro> & confirmação');

        $this->assertCount(1, $renderer->calls);
        $this->assertSame('Ficha &lt;cadastro&gt; &amp; confirmação', $renderer->calls[0]['placeholders']['title']);
        $this->assertSame('https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=55&amp;id=66', $renderer->calls[0]['placeholders']['urlPDF']);
        $this->assertSame([
            'CONTATO[EMAIL]' => [
                'question' => 'Nome &lt;b&gt;visível&lt;/b&gt;',
                'answer' => 'Ada &amp; Bob',
                'raw' => '&quot;quoted&quot;',
            ],
        ], $renderer->calls[0]['fieldValues']);
        $this->assertSame([
            [
                'chat_id' => 'chat-99',
                'text' => 'Mensagem segura',
                'parse_mode' => 'HTML',
            ],
        ], $requests);
    }

    public function testSendMessageOmitsParseModeWhenTextModeIsSelected(): void
    {
        $plugin = $this->newPlugin();
        $plugin->setMockedSettings([
            'SendMessage' => true,
            'DefaultText' => 'Texto puro',
            'ParseMode' => 'Text',
        ]);

        $requests = [];
        $telegram = new Api(static function (array $params) use (&$requests): array {
            $requests[] = $params;

            return ['ok' => true];
        });

        $plugin->sendMessage(55, 66, 'chat-99', $telegram, 'Título');

        $this->assertCount(1, $requests);
        $this->assertSame('chat-99', $requests[0]['chat_id']);
        $this->assertSame('Texto puro', $requests[0]['text']);
        $this->assertArrayNotHasKey('parse_mode', $requests[0]);
    }

    public function testBeforeSurveySettingsPublishesCurrentValuesAndCheckboxDefaults(): void
    {
        $plugin = $this->newPlugin();
        $plugin->setMockedSettings([
            'AuthToken' => 'bot-token',
            'ChatId' => 'chat-42',
            'ParseMode' => 'Markdown',
            'DefaultText' => 'Mensagem padrão',
        ]);

        $event = $this->newEvent([
            'survey' => 77,
        ]);
        $plugin->setMockedEvent($event);

        $plugin->beforeSurveySettings();

        $definition = $event->sets['surveysettings.LSTelegramNotify'] ?? null;

        $this->assertIsArray($definition);
        $this->assertSame(LSTelegramNotifyPluginDouble::class, $definition['name']);
        $this->assertTrue($definition['settings']['Enable']['current']);
        $this->assertSame('Enable telegram notifications', $definition['settings']['Enable']['label']);
        $this->assertSame('Auth Token', $definition['settings']['AuthToken']['label']);
        $this->assertSame('bot-token', $definition['settings']['AuthToken']['current']);
        $this->assertSame('chat-42', $definition['settings']['ChatId']['current']);
        $this->assertSame('Markdown', $definition['settings']['ParseMode']['current']);
        $this->assertSame('Text', $definition['settings']['ParseMode']['options']['Text']);
        $this->assertFalse($definition['settings']['SendPdf']['current']);
        $this->assertFalse($definition['settings']['SendCsv']['current']);
        $this->assertFalse($definition['settings']['SendMessage']['current']);
        $this->assertSame('Mensagem padrão', $definition['settings']['DefaultText']['current']);
    }

    public function testNewSurveySettingsPersistsEachIncomingSurveySetting(): void
    {
        $plugin = $this->newPlugin();
        $event = $this->newEvent([
            'survey' => 88,
            'settings' => [
                'ChatId' => 'chat-88',
                'SendMessage' => true,
                'DefaultText' => 'Olá',
            ],
        ]);
        $plugin->setMockedEvent($event);

        $plugin->newSurveySettings();

        $this->assertSame([
            [
                'name' => 'ChatId',
                'value' => 'chat-88',
                'model' => 'Survey',
                'id' => 88,
            ],
            [
                'name' => 'SendMessage',
                'value' => true,
                'model' => 'Survey',
                'id' => 88,
            ],
            [
                'name' => 'DefaultText',
                'value' => 'Olá',
                'model' => 'Survey',
                'id' => 88,
            ],
        ], $plugin->getSavedSettings());
    }

    private function newPlugin(): LSTelegramNotifyPluginDouble
    {
        return (new \ReflectionClass(LSTelegramNotifyPluginDouble::class))->newInstanceWithoutConstructor();
    }

    private function newEvent(array $values): object
    {
        return new class($values) {
            /** @var array<string, mixed> */
            private array $values;

            /** @var array<string, mixed> */
            public array $sets = [];

            /** @param array<string, mixed> $values */
            public function __construct(array $values)
            {
                $this->values = $values;
            }

            public function get(string $name)
            {
                return $this->values[$name] ?? null;
            }

            public function set(string $name, $value): void
            {
                $this->sets[$name] = $value;
            }
        };
    }
}
