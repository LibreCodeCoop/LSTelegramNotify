<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Plugin\DefaultTextHelpBuilder;
use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldPlaceholderCatalogProvider;
use PHPUnit\Framework\TestCase;

class DefaultTextHelpBuilderTest extends TestCase
{
    public function testBuildReturnsGlobalHelpWithoutQueryingFieldCatalogWhenSurveyIdIsMissing(): void
    {
        $provider = new class extends SurveyFieldPlaceholderCatalogProvider {
            /** @var int[] */
            public array $surveyIds = [];

            public function getFieldPlaceholderCatalog(int $surveyId): array
            {
                $this->surveyIds[] = $surveyId;

                return [];
            }
        };

        $builder = new DefaultTextHelpBuilder($provider);
        $help = $builder->build();

        $this->assertSame([], $provider->surveyIds);
        $this->assertStringContainsString('{{field:FIELD_CODE.answer}}', $help);
        $this->assertStringContainsString('{{urlAttachments}}', $help);
        $this->assertStringContainsString('survey-specific plugin settings', $help);
        $this->assertStringNotContainsString('No field codes could be identified for this survey.', $help);
    }

    public function testBuildReturnsNoFieldCodesMessageWhenSurveyCatalogIsEmpty(): void
    {
        $provider = new class extends SurveyFieldPlaceholderCatalogProvider {
            public function getFieldPlaceholderCatalog(int $surveyId): array
            {
                return [];
            }
        };

        $builder = new DefaultTextHelpBuilder($provider);
        $help = $builder->build(77);

        $this->assertStringContainsString('No field codes could be identified for this survey.', $help);
    }

    public function testBuildIncludesEscapedFieldCodesWhenSurveyCatalogIsAvailable(): void
    {
        $provider = new class extends SurveyFieldPlaceholderCatalogProvider {
            public function getFieldPlaceholderCatalog(int $surveyId): array
            {
                return [
                    'CONTATO[EMAIL]' => 'E-mail <principal>',
                    'NOME' => 'Nome & sobrenome',
                ];
            }
        };

        $builder = new DefaultTextHelpBuilder($provider);
        $help = $builder->build(77);

        $this->assertStringContainsString('Field codes available in this survey', $help);
        $this->assertStringContainsString('<code>CONTATO[EMAIL]</code> &mdash; E-mail &lt;principal&gt;', $help);
        $this->assertStringContainsString('<code>NOME</code> &mdash; Nome &amp; sobrenome', $help);
    }
}
