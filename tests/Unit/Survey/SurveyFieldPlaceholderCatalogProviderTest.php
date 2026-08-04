<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldPlaceholderCatalogProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SurveyFieldPlaceholderCatalogProviderTest extends TestCase
{
    protected function setUp(): void
    {
        \Survey::$findByPkHandler = null;
        \FieldMapRuntimeMock::$createFieldMapHandler = null;
        \viewHelper::$getFieldTextHandler = null;
        \viewHelper::$getFieldCodeHandler = null;
    }

    #[DataProvider('emptyCatalogScenarios')]
    public function testGetFieldPlaceholderCatalogReturnsEmptyForEmptyScenarios(callable $configureRuntime, int $surveyId): void
    {
        $configureRuntime();

        $provider = new SurveyFieldPlaceholderCatalogProvider();

        $this->assertSame([], $provider->getFieldPlaceholderCatalog($surveyId));
    }

    public function testGetFieldPlaceholderCatalogBuildsSortedUniqueLabelsAndFallsBackToFieldName(): void
    {
        \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'pt-BR'];
        \FieldMapRuntimeMock::$createFieldMapHandler = static fn ($survey, $style, $full, $flatten, $language): array => [
            '12345X1X1' => ['name' => 'email-field'],
            '12345X1X10' => ['name' => 'duplicate-email-field'],
            '12345X1X2' => ['name' => 'name-field'],
            '12345X1X3' => ['name' => 'fallback-label-field'],
            '12345X1X4' => ['name' => 'blank-code-field'],
        ];
        \viewHelper::$getFieldCodeHandler = static function (array $field, array $options): string {
            switch ($field['name']) {
                case 'email-field':
                    return 'CONTATO[EMAIL]';
                case 'duplicate-email-field':
                    return 'CONTATO[EMAIL]';
                case 'name-field':
                    return 'NOME';
                case 'fallback-label-field':
                    return 'SEM_LABEL';
                default:
                    return '';
            }
        };
        \viewHelper::$getFieldTextHandler = static function (array $field, array $options): string {
            switch ($field['name']) {
                case 'email-field':
                    return 'E-mail para contato';
                case 'duplicate-email-field':
                    return 'Outro e-mail';
                case 'name-field':
                    return 'Nome completo';
                case 'fallback-label-field':
                    return '   ';
                default:
                    return '';
            }
        };

        $provider = new SurveyFieldPlaceholderCatalogProvider();

        $this->assertSame([
            'CONTATO[EMAIL]' => 'E-mail para contato',
            'NOME' => 'Nome completo',
            'SEM_LABEL' => '12345X1X3',
        ], $provider->getFieldPlaceholderCatalog(77));
    }

    public static function emptyCatalogScenarios(): array
    {
        return [
            'survey does not exist' => [
                static function (): void {
                },
                1,
            ],
            'field map is empty' => [
                static function (): void {
                    \Survey::$findByPkHandler = static fn (int $id) => (object) ['language' => 'pt-BR'];
                    \FieldMapRuntimeMock::$createFieldMapHandler = static fn ($survey, $style, $full, $flatten, $language): array => [];
                },
                1,
            ],
        ];
    }
}
