<?php

namespace LibreCodeCoop\LSTelegramNotify\Template;

class MessageTemplateRenderer
{
    /**
     * @param array<string, string> $placeholders
     * @param array<string, array<string, string>> $fieldValues
     */
    public function render(string $template, array $placeholders, array $fieldValues): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/',
            function (array $matches) use ($placeholders, $fieldValues): string {
                $replacement = $this->resolvePlaceholder($matches[1], $placeholders, $fieldValues);

                return $replacement ?? $matches[0];
            },
            $template
        );

        if ($rendered === null) {
            return $template;
        }

        return strtr($rendered, [
            '{surveyId}' => $placeholders['surveyId'] ?? '',
            '{responseId}' => $placeholders['responseId'] ?? '',
            '{urlPDF}' => $placeholders['urlPDF'] ?? '',
            '{title}' => $placeholders['title'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $placeholders
     * @param array<string, array<string, string>> $fieldValues
     */
    protected function resolvePlaceholder(string $placeholder, array $placeholders, array $fieldValues): ?string
    {
        $placeholder = trim($placeholder);

        if (array_key_exists($placeholder, $placeholders)) {
            return $placeholders[$placeholder];
        }

        if (!preg_match('/^field:(.+)\.(question|answer|raw)$/', $placeholder, $matches)) {
            if (isset($fieldValues[$placeholder]) && array_key_exists('question', $fieldValues[$placeholder])) {
                return $fieldValues[$placeholder]['question'];
            }

            if (preg_match('/^(.+?)_(question|answer|raw)$/', $placeholder, $matches) === 1) {
                $fieldCode = $matches[1];
                $fieldProperty = $matches[2];

                if (isset($fieldValues[$fieldCode]) && array_key_exists($fieldProperty, $fieldValues[$fieldCode])) {
                    return $fieldValues[$fieldCode][$fieldProperty];
                }
            }

            if (preg_match('/^(question|answer|raw)_(.+)$/', $placeholder, $matches) === 1) {
                $fieldProperty = $matches[1];
                $fieldCode = $matches[2];

                if (isset($fieldValues[$fieldCode]) && array_key_exists($fieldProperty, $fieldValues[$fieldCode])) {
                    return $fieldValues[$fieldCode][$fieldProperty];
                }
            }

            return null;
        }

        $fieldCode = $matches[1];
        $fieldProperty = $matches[2];

        if (!isset($fieldValues[$fieldCode]) || !array_key_exists($fieldProperty, $fieldValues[$fieldCode])) {
            return null;
        }

        return $fieldValues[$fieldCode][$fieldProperty];
    }
}
