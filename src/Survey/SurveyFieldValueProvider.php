<?php

namespace LibreCodeCoop\LSTelegramNotify\Survey;

class SurveyFieldValueProvider
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getFieldValues(int $surveyId, int $responseId): array
    {
        $survey = \Survey::model()->findByPk($surveyId);
        if ($survey === null) {
            return [];
        }

        $response = \SurveyDynamic::model($surveyId)->findByPk($responseId);
        if ($response === null) {
            return [];
        }

        $responseAttributes = $this->extractResponseAttributes($response);
        $responseLanguage = isset($responseAttributes['startlanguage'])
            && is_string($responseAttributes['startlanguage'])
            && $responseAttributes['startlanguage'] !== ''
            ? $responseAttributes['startlanguage']
            : $survey->language;

        \Yii::import('application.helpers.admin.export.*', true);
        \Yii::import('application.helpers.viewHelper', true);

        $formattingOptions = new \FormattingOptions();
        $surveyDao = new \SurveyDao();
        $surveyData = $surveyDao->loadSurveyById($surveyId, $responseLanguage, $formattingOptions);
        $translator = new \Translator();
        $fieldValues = [];

        foreach ($surveyData->fieldMap as $fieldName => $field) {
            if (!array_key_exists($fieldName, $responseAttributes)) {
                continue;
            }

            $value = $this->normalizeTemplateValue($responseAttributes[$fieldName]);
            $entry = [
                'question' => trim((string) \viewHelper::getFieldText($field, [
                    'flat' => true,
                    'separator' => ['[', ']'],
                    'afterquestion' => ' ',
                ])),
                'answer' => (string) ($surveyData->getFullAnswer($fieldName, $value, $translator, $responseLanguage) ?? ''),
                'raw' => $value,
            ];

            $fieldCode = \viewHelper::getFieldCode($field, ['separator' => ['[', ']']]);
            if ($fieldCode !== '' && !array_key_exists($fieldCode, $fieldValues)) {
                $fieldValues[$fieldCode] = $entry;
            }

            if (!array_key_exists($fieldName, $fieldValues)) {
                $fieldValues[$fieldName] = $entry;
            }
        }

        return $fieldValues;
    }

    /**
     * @param array<string, mixed>|object $response
     *
     * @return array<string, mixed>
     */
    protected function extractResponseAttributes($response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && isset($response->attributes) && is_array($response->attributes)) {
            return $response->attributes;
        }

        return [];
    }

    /**
     * @param mixed $value
     */
    protected function normalizeTemplateValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'normalizeTemplateValue'], $value));
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
