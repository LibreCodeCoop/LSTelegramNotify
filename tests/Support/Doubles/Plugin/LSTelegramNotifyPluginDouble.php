<?php

use LibreCodeCoop\LSTelegramNotify\Plugin\LSTelegramNotifyPlugin;

class LSTelegramNotifyPluginDouble extends LSTelegramNotifyPlugin
{
    /** @var array<string, mixed> */
    private array $mockedSettings = [];

    /** @var array<string, array<string, string>> */
    private array $mockedTemplateFields = [];

    /** @param array<string, mixed> $settings */
    public function setMockedSettings(array $settings): void
    {
        $this->mockedSettings = $settings;
    }

    /** @param array<string, array<string, string>> $fields */
    public function setMockedTemplateFields(array $fields): void
    {
        $this->mockedTemplateFields = $fields;
    }

    protected function get($key = null, $model = null, $id = null, $default = null)
    {
        return array_key_exists((string) $key, $this->mockedSettings)
            ? $this->mockedSettings[(string) $key]
            : $default;
    }

    /** @return array<string, array<string, string>> */
    protected function getTemplateFieldValues(int $surveyId, int $responseId): array
    {
        return $this->mockedTemplateFields;
    }
}
