<?php

use LibreCodeCoop\LSTelegramNotify\Plugin\LSTelegramNotifyPlugin;
use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;

class LSTelegramNotifyPluginDouble extends LSTelegramNotifyPlugin
{
    public string $id = 'LSTelegramNotify';

    /** @var array<string, mixed> */
    private array $mockedSettings = [];

    private ?object $mockedEvent = null;

    private ?MessageTemplateRenderer $mockedMessageTemplateRenderer = null;

    private ?SurveyFieldValueProvider $mockedSurveyFieldValueProvider = null;

    /** @var array<int, array{name: string, value: mixed, model: mixed, id: mixed}> */
    private array $savedSettings = [];

    /** @var string[] */
    private array $subscribedEvents = [];

    /** @param array<string, mixed> $settings */
    public function setMockedSettings(array $settings): void
    {
        $this->mockedSettings = $settings;
    }

    public function setMockedEvent(object $event): void
    {
        $this->mockedEvent = $event;
    }

    public function setMockedMessageTemplateRenderer(MessageTemplateRenderer $renderer): void
    {
        $this->mockedMessageTemplateRenderer = $renderer;
    }

    public function setMockedSurveyFieldValueProvider(SurveyFieldValueProvider $provider): void
    {
        $this->mockedSurveyFieldValueProvider = $provider;
    }

    /** @return array<int, array{name: string, value: mixed, model: mixed, id: mixed}> */
    public function getSavedSettings(): array
    {
        return $this->savedSettings;
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return $this->subscribedEvents;
    }

    /** @return array<string, mixed> */
    public function getSettingsDefinition(): array
    {
        return $this->settings;
    }

    protected function get($key = null, $model = null, $id = null, $default = null)
    {
        return array_key_exists((string) $key, $this->mockedSettings)
            ? $this->mockedSettings[(string) $key]
            : $default;
    }

    protected function getEvent(): object
    {
        if ($this->mockedEvent === null) {
            throw new \LogicException('Mocked event was not configured.');
        }

        return $this->mockedEvent;
    }

    protected function set($name, $value, $model = null, $id = null): void
    {
        $this->savedSettings[] = [
            'name' => (string) $name,
            'value' => $value,
            'model' => $model,
            'id' => $id,
        ];
    }

    protected function subscribe($eventName): void
    {
        $this->subscribedEvents[] = (string) $eventName;
    }

    protected function createMessageTemplateRenderer(): MessageTemplateRenderer
    {
        return $this->mockedMessageTemplateRenderer ?? parent::createMessageTemplateRenderer();
    }

    protected function createSurveyFieldValueProvider(): SurveyFieldValueProvider
    {
        return $this->mockedSurveyFieldValueProvider ?? parent::createSurveyFieldValueProvider();
    }
}
