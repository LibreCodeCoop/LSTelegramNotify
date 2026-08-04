<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/Template/MessageTemplateRenderer.php';

use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessageTemplateRendererTest extends TestCase
{
    #[DataProvider('renderProvider')]
    public function testRender(string $template, string $expected): void
    {
        $renderer = new MessageTemplateRenderer();

        $this->assertSame($expected, $renderer->render($template, self::placeholders(), self::fieldValues()));
    }

    public static function renderProvider(): array
    {
        return [
            'renders metadata placeholders using mustache and legacy syntax' => [
                'Survey={{surveyId}};Response={responseId};Title={{title}};Pdf={urlPDF}',
                'Survey=55;Response=66;Title=Ficha de cadastro;Pdf=https://example.test/admin/responses/sa/viewquexmlpdf?surveyid=55&id=66',
            ],
            'renders explicit field placeholders' => [
                "Pergunta: {{field:CONTATO[EMAIL].question}}\nResposta: {{field:CONTATO[EMAIL].answer}}",
                "Pergunta: E-mail para contato\nResposta: ada@example.test",
            ],
            'renders shorthand field placeholders' => [
                "Pergunta: {{CONTATO[EMAIL]}}\nResposta A: {{CONTATO[EMAIL]_answer}}\nResposta B: {{answer_CONTATO[EMAIL]}}\nRaw: {{raw_CONTATO[EMAIL]}}",
                "Pergunta: E-mail para contato\nResposta A: ada@example.test\nResposta B: ada@example.test\nRaw: ada@example.test",
            ],
            'keeps unknown placeholders untouched' => [
                'Desconhecido={{naoExiste}}',
                'Desconhecido={{naoExiste}}',
            ],
        ];
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
