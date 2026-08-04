<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

class MessageTemplateRendererTest extends TestCase
{
    public function testRenderReplacesMetadataPlaceholdersAcrossLegacyAndMustacheSyntax(): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame(
            'Survey=55;Response=66;Title=Ficha de cadastro;Pdf=https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=55&id=66',
            $renderer->render(
                'Survey={{surveyId}};Response={responseId};Title={{title}};Pdf={urlPDF}',
                self::placeholders(),
                self::fieldValues()
            )
        );
    }

    public function testRenderTrimsWhitespaceInsideMustachePlaceholders(): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame(
            'Survey=55;Resposta=ada@example.test',
            $renderer->render(
                'Survey={{  surveyId  }};Resposta={{ field:CONTATO[EMAIL].answer }}',
                self::placeholders(),
                self::fieldValues()
            )
        );
    }

    public function testRenderResolvesExplicitFieldPlaceholders(): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame(
            "Pergunta: E-mail para contato\nResposta: ada@example.test\nValor bruto: ada@example.test",
            $renderer->render(
                "Pergunta: {{field:CONTATO[EMAIL].question}}\nResposta: {{field:CONTATO[EMAIL].answer}}\nValor bruto: {{field:CONTATO[EMAIL].raw}}",
                self::placeholders(),
                self::fieldValues()
            )
        );
    }

    public function testRenderResolvesShorthandFieldPlaceholders(): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame(
            "Pergunta: E-mail para contato\nResposta A: ada@example.test\nResposta B: ada@example.test\nRaw: ada@example.test",
            $renderer->render(
                "Pergunta: {{CONTATO[EMAIL]}}\nResposta A: {{CONTATO[EMAIL]_answer}}\nResposta B: {{answer_CONTATO[EMAIL]}}\nRaw: {{raw_CONTATO[EMAIL]}}",
                self::placeholders(),
                self::fieldValues()
            )
        );
    }

    public function testRenderLeavesUnknownPlaceholdersUntouched(): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame(
            'Desconhecido={{naoExiste}};Incompleto={{field:CONTATO[EMAIL].missing}}',
            $renderer->render(
                'Desconhecido={{naoExiste}};Incompleto={{field:CONTATO[EMAIL].missing}}',
                self::placeholders(),
                self::fieldValues()
            )
        );
    }

    /** @return array<string, string> */
    private static function placeholders(): array
    {
        return [
            'surveyId' => '55',
            'responseId' => '66',
            'urlPDF' => 'https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=55&id=66',
            'title' => 'Ficha de cadastro',
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function fieldValues(): array
    {
        return [
            'CONTATO[EMAIL]' => [
                'question' => 'E-mail para contato',
                'answer' => 'ada@example.test',
                'raw' => 'ada@example.test',
            ],
        ];
    }
}
