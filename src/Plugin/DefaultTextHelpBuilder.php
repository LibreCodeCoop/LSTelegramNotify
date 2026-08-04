<?php

namespace LibreCodeCoop\LSTelegramNotify\Plugin;

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldPlaceholderCatalogProvider;

class DefaultTextHelpBuilder
{
    private SurveyFieldPlaceholderCatalogProvider $fieldPlaceholderCatalogProvider;

    public function __construct(SurveyFieldPlaceholderCatalogProvider $fieldPlaceholderCatalogProvider)
    {
        $this->fieldPlaceholderCatalogProvider = $fieldPlaceholderCatalogProvider;
    }

    public function build(?int $surveyId = null): string
    {
        $sections = [
            '<p>Available placeholders for the Telegram message template:</p>',
            '<p><strong>Metadata placeholders</strong></p>',
            '<ul>' . $this->buildMetadataPlaceholderHelpItems() . '</ul>',
            '<p><strong>Survey field syntax</strong></p>',
            '<ul>' . $this->buildFieldSyntaxHelpItems() . '</ul>',
            '<p>When <code>ParseMode</code> is <code>HTML</code>, placeholder values are escaped automatically before rendering.</p>',
        ];

        if ($surveyId === null) {
            $sections[] = '<p>Open the survey-specific plugin settings to see the field codes available for a particular survey.</p>';

            return implode("\n", $sections);
        }

        $fieldCatalog = $this->fieldPlaceholderCatalogProvider->getFieldPlaceholderCatalog($surveyId);

        if ($fieldCatalog === []) {
            $sections[] = '<p>No field codes could be identified for this survey.</p>';

            return implode("\n", $sections);
        }

        $fieldItems = [];

        foreach ($fieldCatalog as $fieldCode => $label) {
            $fieldItems[] = sprintf(
                '<li><code>%s</code> &mdash; %s</li>',
                htmlspecialchars($fieldCode, ENT_QUOTES),
                htmlspecialchars($label, ENT_QUOTES)
            );
        }

        $sections[] = '<p><strong>Field codes available in this survey</strong></p>';
        $sections[] = '<ul>' . implode('', $fieldItems) . '</ul>';

        return implode("\n", $sections);
    }

    protected function buildMetadataPlaceholderHelpItems(): string
    {
        $placeholders = [
            ['tokens' => ['{title}', '{{title}}'], 'description' => 'Survey title'],
            ['tokens' => ['{surveyId}', '{{surveyId}}'], 'description' => 'Survey identifier'],
            ['tokens' => ['{responseId}', '{{responseId}}'], 'description' => 'Response identifier'],
            ['tokens' => ['{urlPDF}', '{{urlPDF}}'], 'description' => 'PDF download URL'],
            ['tokens' => ['{urlSurvey}', '{{urlSurvey}}'], 'description' => 'Survey administration URL'],
            ['tokens' => ['{urlDetails}', '{{urlDetails}}'], 'description' => 'Response details URL'],
            ['tokens' => ['{urlEdit}', '{{urlEdit}}'], 'description' => 'Response edit URL'],
            ['tokens' => ['{urlExport}', '{{urlExport}}'], 'description' => 'Response export URL'],
            ['tokens' => ['{urlAttachments}', '{{urlAttachments}}'], 'description' => 'Response attachments URL'],
        ];

        $items = [];

        foreach ($placeholders as $placeholder) {
            $items[] = sprintf(
                '<li>%s &mdash; %s</li>',
                $this->formatHelpCodeTokens($placeholder['tokens']),
                htmlspecialchars($placeholder['description'], ENT_QUOTES)
            );
        }

        return implode('', $items);
    }

    protected function buildFieldSyntaxHelpItems(): string
    {
        $placeholders = [
            ['token' => '{{field:FIELD_CODE.question}}', 'description' => 'Question text for a survey field'],
            ['token' => '{{field:FIELD_CODE.answer}}', 'description' => 'Formatted answer for a survey field'],
            ['token' => '{{field:FIELD_CODE.raw}}', 'description' => 'Raw stored value for a survey field'],
            ['token' => '{{FIELD_CODE}}', 'description' => 'Shortcut for the question text'],
            ['token' => '{{FIELD_CODE_answer}}', 'description' => 'Shortcut for the formatted answer'],
            ['token' => '{{answer_FIELD_CODE}}', 'description' => 'Alternative answer shortcut'],
            ['token' => '{{raw_FIELD_CODE}}', 'description' => 'Shortcut for the raw stored value'],
        ];

        $items = [];

        foreach ($placeholders as $placeholder) {
            $items[] = sprintf(
                '<li><code>%s</code> &mdash; %s</li>',
                htmlspecialchars($placeholder['token'], ENT_QUOTES),
                htmlspecialchars($placeholder['description'], ENT_QUOTES)
            );
        }

        return implode('', $items);
    }

    /**
     * @param array<int, string> $tokens
     */
    protected function formatHelpCodeTokens(array $tokens): string
    {
        $formatted = array_map(function (string $token): string {
            return '<code>' . htmlspecialchars($token, ENT_QUOTES) . '</code>';
        }, $tokens);

        return implode(' / ', $formatted);
    }
}
