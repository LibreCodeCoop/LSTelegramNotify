<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/Survey/SurveyFieldValueProvider.php';

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use PHPUnit\Framework\TestCase;

class SurveyFieldValueProviderTest extends TestCase
{
    protected function setUp(): void
    {
        \Survey::$findByPkHandler = null;
        \SurveyDynamic::$findByPkHandler = null;
        \SurveyDao::$loadSurveyByIdHandler = null;
        \viewHelper::$getFieldTextHandler = null;
        \viewHelper::$getFieldCodeHandler = null;
    }

    public function testGetFieldValuesBuildsEntriesByFieldCodeAndFieldName(): void
    {
        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'pt-BR'];
        \SurveyDynamic::$findByPkHandler = static fn (int $surveyId, int $responseId) => (object) [
            'attributes' => [
                '12345X1X1' => 'ada@example.test',
                'startlanguage' => 'pt-BR',
            ],
        ];
        \SurveyDao::$loadSurveyByIdHandler = static fn (int $surveyId, string $language, \FormattingOptions $options) => new class {
            /** @var array<string, array<string, string>> */
            public array $fieldMap = [
                '12345X1X1' => ['title' => 'Contato'],
            ];

            public function getFullAnswer(string $fieldName, string $value, \Translator $translator, string $responseLanguage): string
            {
                return strtoupper($value);
            }
        };
        \viewHelper::$getFieldTextHandler = static fn (array $field, array $options): string => 'E-mail para contato';
        \viewHelper::$getFieldCodeHandler = static fn (array $field, array $options): string => 'CONTATO[EMAIL]';

        $provider = new SurveyFieldValueProvider();

        $values = $provider->getFieldValues(55, 66);
        $expected = [
            'question' => 'E-mail para contato',
            'answer' => 'ADA@EXAMPLE.TEST',
            'raw' => 'ada@example.test',
        ];

        $this->assertSame($expected, $values['CONTATO[EMAIL]']);
        $this->assertSame($expected, $values['12345X1X1']);
    }

    public function testGetFieldValuesNormalizesArrayValuesAndFallsBackToSurveyLanguage(): void
    {
        $capturedLanguage = null;

        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'en'];
        \SurveyDynamic::$findByPkHandler = static fn (int $surveyId, int $responseId) => [
            '12345X1X1' => ['Ada', 'Lovelace'],
        ];
        \SurveyDao::$loadSurveyByIdHandler = static function (int $surveyId, string $language, \FormattingOptions $options) use (&$capturedLanguage) {
            $capturedLanguage = $language;

            return new class {
                /** @var array<string, array<string, string>> */
                public array $fieldMap = [
                    '12345X1X1' => ['title' => 'Nome'],
                ];

                public function getFullAnswer(string $fieldName, string $value, \Translator $translator, string $responseLanguage): string
                {
                    return 'Answer: ' . $value;
                }
            };
        };
        \viewHelper::$getFieldTextHandler = static fn (array $field, array $options): string => 'Nome completo';
        \viewHelper::$getFieldCodeHandler = static fn (array $field, array $options): string => 'NOME';

        $provider = new SurveyFieldValueProvider();

        $values = $provider->getFieldValues(10, 20);

        $this->assertSame('en', $capturedLanguage);
        $this->assertSame('Ada, Lovelace', $values['NOME']['raw']);
        $this->assertSame('Answer: Ada, Lovelace', $values['NOME']['answer']);
    }

    public function testGetFieldValuesReturnsEmptyWhenSurveyOrResponseDoesNotExist(): void
    {
        $provider = new SurveyFieldValueProvider();

        $this->assertSame([], $provider->getFieldValues(1, 2));

        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'en'];

        $this->assertSame([], $provider->getFieldValues(1, 2));
    }
}
