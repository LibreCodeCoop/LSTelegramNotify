<?php

namespace LibreCodeCoop\LSTelegramNotify\Survey;

class SurveyFieldPlaceholderCatalogProvider
{
    /**
     * @return array<string, string>
     */
    public function getFieldPlaceholderCatalog(int $surveyId): array
    {
        $survey = \Survey::model()->findByPk($surveyId);

        if ($survey === null) {
            return [];
        }

        \Yii::import('application.helpers.viewHelper', true);
        $fieldMap = \createFieldMap($survey, 'full', true, false, $survey->language);

        if (!is_array($fieldMap)) {
            return [];
        }

        $fieldCatalog = [];

        foreach ($fieldMap as $fieldName => $field) {
            $fieldCode = trim((string) \viewHelper::getFieldCode($field, ['separator' => ['[', ']']]));

            if ($fieldCode === '') {
                continue;
            }

            $label = trim((string) \viewHelper::getFieldText($field, [
                'flat' => true,
                'separator' => ['[', ']'],
                'afterquestion' => ' ',
            ]));

            if ($label === '') {
                $label = (string) $fieldName;
            }

            if (!array_key_exists($fieldCode, $fieldCatalog)) {
                $fieldCatalog[$fieldCode] = $label;
            }
        }

        ksort($fieldCatalog, SORT_NATURAL | SORT_FLAG_CASE);

        return $fieldCatalog;
    }
}
