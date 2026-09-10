<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use PHPUnit\Framework\TestCase;

class SurveyFieldValueProviderTest extends TestCase
{
    protected function setUp(): void
    {
        \Survey::$findByPkHandler = null;
        \SurveyDynamic::$findByPkHandler = null;
        \SurveyDynamic::$getMaxIdHandler = null;
        \SurveyDao::$loadSurveyByIdHandler = null;
        \viewHelper::$getFieldTextHandler = null;
        \viewHelper::$getFieldCodeHandler = null;
    }

    public function testGetFieldValuesReturnsEmptyWhenSurveyDoesNotExist(): void
    {
        $provider = new SurveyFieldValueProvider();

        $this->assertSame([], $provider->getFieldValues(1, 2));
    }

    public function testGetFieldValuesReturnsEmptyWhenResponseDoesNotExist(): void
    {
        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'en'];

        $provider = new SurveyFieldValueProvider();

        $this->assertSame([], $provider->getFieldValues(1, 2));
    }

    public function testGetFieldValuesBuildsEntriesUsingResponseLanguageWhenAvailable(): void
    {
        $spy = (object) [
            'loadedLanguage' => null,
            'fullAnswerCalls' => [],
        ];

        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'en'];
        \SurveyDynamic::$findByPkHandler = static fn (int $surveyId, int $responseId) => (object) [
            'attributes' => [
                '12345X1X1' => 'ada@example.test',
                'startlanguage' => 'pt-BR',
            ],
        ];
        \SurveyDao::$loadSurveyByIdHandler = static function (int $surveyId, string $language, \FormattingOptions $options) use ($spy) {
            $spy->loadedLanguage = $language;

            return new class($spy) {
                /** @var array<string, array<string, string>> */
                public array $fieldMap = [
                    '12345X1X1' => ['title' => 'Contato'],
                ];

                private object $spy;

                public function __construct(object $spy)
                {
                    $this->spy = $spy;
                }

                public function getFullAnswer(string $fieldName, string $value, \Translator $translator, string $responseLanguage): string
                {
                    $this->spy->fullAnswerCalls[] = [
                        'fieldName' => $fieldName,
                        'value' => $value,
                        'responseLanguage' => $responseLanguage,
                    ];

                    return strtoupper($value);
                }
            };
        };
        \viewHelper::$getFieldTextHandler = static fn (array $field, array $options): string => '  E-mail para contato  ';
        \viewHelper::$getFieldCodeHandler = static fn (array $field, array $options): string => 'CONTATO[EMAIL]';

        $provider = new SurveyFieldValueProvider();

        $values = $provider->getFieldValues(55, 66);
        $expected = [
            'question' => 'E-mail para contato',
            'answer' => 'ADA@EXAMPLE.TEST',
            'raw' => 'ada@example.test',
        ];

        $this->assertSame('pt-BR', $spy->loadedLanguage);
        $this->assertSame([
            [
                'fieldName' => '12345X1X1',
                'value' => 'ada@example.test',
                'responseLanguage' => 'pt-BR',
            ],
        ], $spy->fullAnswerCalls);
        $this->assertSame($expected, $values['CONTATO[EMAIL]']);
        $this->assertSame($expected, $values['12345X1X1']);
    }

    public function testGetFieldValuesFallsBackToSurveyLanguageAndNormalizesArrayValues(): void
    {
        $spy = (object) [
            'loadedLanguage' => null,
            'fullAnswerCalls' => [],
        ];

        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'en'];
        \SurveyDynamic::$findByPkHandler = static fn (int $surveyId, int $responseId) => [
            '12345X1X1' => ['Ada', 'Lovelace'],
        ];
        \SurveyDao::$loadSurveyByIdHandler = static function (int $surveyId, string $language, \FormattingOptions $options) use ($spy) {
            $spy->loadedLanguage = $language;

            return new class($spy) {
                /** @var array<string, array<string, string>> */
                public array $fieldMap = [
                    '12345X1X1' => ['title' => 'Nome'],
                ];

                private object $spy;

                public function __construct(object $spy)
                {
                    $this->spy = $spy;
                }

                public function getFullAnswer(string $fieldName, string $value, \Translator $translator, string $responseLanguage): string
                {
                    $this->spy->fullAnswerCalls[] = [
                        'fieldName' => $fieldName,
                        'value' => $value,
                        'responseLanguage' => $responseLanguage,
                    ];

                    return 'Answer: ' . $value;
                }
            };
        };
        \viewHelper::$getFieldTextHandler = static fn (array $field, array $options): string => 'Nome completo';
        \viewHelper::$getFieldCodeHandler = static fn (array $field, array $options): string => 'NOME';

        $provider = new SurveyFieldValueProvider();

        $values = $provider->getFieldValues(10, 20);

        $this->assertSame('en', $spy->loadedLanguage);
        $this->assertSame([
            [
                'fieldName' => '12345X1X1',
                'value' => 'Ada, Lovelace',
                'responseLanguage' => 'en',
            ],
        ], $spy->fullAnswerCalls);
        $this->assertSame('Ada, Lovelace', $values['NOME']['raw']);
        $this->assertSame('Answer: Ada, Lovelace', $values['NOME']['answer']);
    }

    public function testGetFieldValuesIgnoresFieldsMissingFromResponse(): void
    {
        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'pt-BR'];
        \SurveyDynamic::$findByPkHandler = static fn (int $surveyId, int $responseId) => [
            '12345X1X1' => 'ada@example.test',
        ];
        \SurveyDao::$loadSurveyByIdHandler = static fn (int $surveyId, string $language, \FormattingOptions $options) => new class {
            /** @var array<string, array<string, string>> */
            public array $fieldMap = [
                '12345X1X1' => ['title' => 'Contato'],
                '12345X1X2' => ['title' => 'Telefone'],
            ];

            public function getFullAnswer(string $fieldName, string $value, \Translator $translator, string $responseLanguage): string
            {
                return strtoupper($value);
            }
        };
        \viewHelper::$getFieldTextHandler = static fn (array $field, array $options): string => $field['title'];
        \viewHelper::$getFieldCodeHandler = static function (array $field, array $options): string {
            return $field['title'] === 'Contato' ? 'CONTATO[EMAIL]' : 'CONTATO[PHONE]';
        };

        $provider = new SurveyFieldValueProvider();

        $values = $provider->getFieldValues(55, 66);

        $this->assertArrayHasKey('CONTATO[EMAIL]', $values);
        $this->assertArrayNotHasKey('CONTATO[PHONE]', $values);
        $this->assertArrayNotHasKey('12345X1X2', $values);
    }
}
