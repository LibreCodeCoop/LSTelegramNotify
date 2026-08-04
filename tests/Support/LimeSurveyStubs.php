<?php

class AppRuntimeMock
{
    public static $createAbsoluteUrlHandler;

    public function createAbsoluteUrl($route, array $params = []): string
    {
        if (is_callable(self::$createAbsoluteUrlHandler)) {
            return call_user_func(self::$createAbsoluteUrlHandler, $route, $params);
        }

        $query = http_build_query($params);
        return 'https://example.test' . $route . ($query !== '' ? '?' . $query : '');
    }

    public function loadHelper($name): void
    {
    }
}

function App(): AppRuntimeMock
{
    static $app;

    if (!$app instanceof AppRuntimeMock) {
        $app = new AppRuntimeMock();
    }

    return $app;
}

class PluginBase
{
}

class Yii
{
    public static function import($name, $force = false)
    {
    }

    public static function app()
    {
        return new class {
            public function getLanguage()
            {
                return 'en';
            }
        };
    }
}

class Survey
{
    public static $findByPkHandler;

    public static function model()
    {
        return new class {
            public function findByPk($id)
            {
                if (is_callable(Survey::$findByPkHandler)) {
                    return call_user_func(Survey::$findByPkHandler, $id);
                }

                return null;
            }
        };
    }
}

class SurveyDynamic
{
    public static $findByPkHandler;
    public static $getMaxIdHandler;

    public static function model($surveyId)
    {
        return new class ($surveyId) {
            private $surveyId;

            public function __construct($surveyId)
            {
                $this->surveyId = $surveyId;
            }

            public function findByPk($responseId)
            {
                if (is_callable(SurveyDynamic::$findByPkHandler)) {
                    return call_user_func(SurveyDynamic::$findByPkHandler, $this->surveyId, $responseId);
                }

                return null;
            }

            public function getMaxId()
            {
                if (is_callable(SurveyDynamic::$getMaxIdHandler)) {
                    return call_user_func(SurveyDynamic::$getMaxIdHandler, $this->surveyId);
                }

                return null;
            }
        };
    }
}

class FormattingOptions
{
}

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

class Translator
{
}

class viewHelper
{
    public static $getFieldTextHandler;
    public static $getFieldCodeHandler;

    public static function getFieldText($field, array $options = []): string
    {
        if (is_callable(self::$getFieldTextHandler)) {
            return call_user_func(self::$getFieldTextHandler, $field, $options);
        }

        return '';
    }

    public static function getFieldCode($field, array $options = []): string
    {
        if (is_callable(self::$getFieldCodeHandler)) {
            return call_user_func(self::$getFieldCodeHandler, $field, $options);
        }

        return '';
    }
}
