<?php

class SurveyDao
{
    public static $loadSurveyByIdHandler;

    public function loadSurveyById($surveyId, $language, $options)
    {
        if (is_callable(self::$loadSurveyByIdHandler)) {
            return call_user_func(self::$loadSurveyByIdHandler, $surveyId, $language, $options);
        }

        return new class {
            /** @var array<string, mixed> */
            public array $fieldMap = [];

            public function getFullAnswer($fieldName, $value, $translator, $responseLanguage): string
            {
                return (string) $value;
            }
        };
    }
}
