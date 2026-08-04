<?php

class PluginEvent
{
    /**
     * @return mixed
     */
    public function get(string $name)
    {
        return null;
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
    }
}

class PluginBase
{
    public string $id = 'LSTelegramNotify';

    public function subscribe(string $eventName): void
    {
    }

    public function getEvent(): PluginEvent
    {
        return new PluginEvent();
    }

    /**
     * @param mixed $surveyId
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $name, ?string $scope = null, $surveyId = null, $default = null)
    {
        return $default;
    }

    /**
     * @param mixed $value
     * @param mixed $surveyId
     */
    public function set(string $name, $value, ?string $scope = null, $surveyId = null): void
    {
    }
}

class LimeSurveyApplication
{
    public function createAbsoluteUrl(string $route, array $params = []): string
    {
        return '';
    }

    public function loadHelper(string $name): void
    {
    }
}

function App(): LimeSurveyApplication
{
    return new LimeSurveyApplication();
}

class YiiApplication
{
    public function getLanguage(): string
    {
        return 'en';
    }
}

class Yii
{
    public static function import(string $name, bool $force = false): void
    {
    }

    public static function app(): YiiApplication
    {
        return new YiiApplication();
    }
}

class SurveyRecord
{
    public string $language = 'en';

    /** @var list<string> */
    public array $allLanguages = ['en'];

    /** @var array<string, mixed> */
    public array $tokenAttributes = [];

    public function getLocalizedTitle(): string
    {
        return '';
    }
}

class SurveyModel
{
    public function findByPk($id): ?SurveyRecord
    {
        return null;
    }
}

class Survey
{
    public static function model(): SurveyModel
    {
        return new SurveyModel();
    }
}

class SurveyResponseRecord
{
    /** @var array<string, mixed> */
    public array $attributes = [];
}

class SurveyDynamicModel
{
    /**
     * @return array<string, mixed>|SurveyResponseRecord|null
     */
    public function findByPk(int $responseId)
    {
        return null;
    }

    public function getMaxId(): ?int
    {
        return null;
    }
}

class SurveyDynamic
{
    public static function model(int $surveyId): SurveyDynamicModel
    {
        return new SurveyDynamicModel();
    }
}

class FormattingOptions
{
    public int $responseMinRecord = 0;
    public int $responseMaxRecord = 0;

    /** @var list<string> */
    public array $selectedColumns = [];

    public string $responseCompletionState = '';
    public string $headingFormat = '';
    public string $answerFormat = '';
    public string $csvFieldSeparator = ',';
    public string $output = '';
}

class Translator
{
}

class SurveyData
{
    /** @var array<string, array<string, mixed>> */
    public array $fieldMap = [];

    public function getFullAnswer(string $fieldName, string $value, Translator $translator, string $responseLanguage): ?string
    {
        return $value;
    }
}

class SurveyDao
{
    public function loadSurveyById(int $surveyId, string $language, FormattingOptions $options): SurveyData
    {
        return new SurveyData();
    }
}

class viewHelper
{
    /**
     * @param array<string, mixed> $field
     *
     * @return string
     */
    public static function getFieldText(array $field, array $options = []): string
    {
        return '';
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function getFieldCode(array $field, array $options = []): string
    {
        return '';
    }
}

/**
 * @return array<string, array<string, mixed>>
 */
function createFieldMap(SurveyRecord $survey, string $style, bool $full, bool $flatten, string $language): array
{
    return [];
}

function quexml_export(int $surveyId, string $language, int $responseId): string
{
    return '';
}

class quexmlpdf
{
    /**
     * @param mixed $quexml
     *
     * @return mixed
     */
    public function createqueXML($quexml)
    {
        return $quexml;
    }

    /**
     * @param mixed $document
     */
    public function create($document): void
    {
    }

    public function Output(string $filename, string $destination): void
    {
    }
}

class ExportSurveyResultsService
{
    public function exportResponses(int $surveyId, string $language, string $format, FormattingOptions $options, string $documentName): string
    {
        return '';
    }
}
