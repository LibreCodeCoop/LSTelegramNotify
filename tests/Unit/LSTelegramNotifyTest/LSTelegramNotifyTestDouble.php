<?php

class LSTelegramNotifyTestDouble extends \LSTelegramNotify
{
    /** @var array<string, mixed> */
    private array $mockedSettings = [];

    /** @param array<string, mixed> $settings */
    public function setMockedSettings(array $settings): void
    {
        $this->mockedSettings = $settings;
    }

    protected function get($key = null, $model = null, $id = null, $default = null)
    {
        return array_key_exists((string) $key, $this->mockedSettings)
            ? $this->mockedSettings[(string) $key]
            : $default;
    }
}
