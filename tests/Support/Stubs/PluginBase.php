<?php

class PluginEvent
{
	public function get(string $name)
	{
		return null;
	}

	public function set(string $name, $value): void
	{
	}
}

class PluginBase
{
	public string $id = 'LSTelegramNotify';

	protected function subscribe(string $eventName): void
	{
	}

	protected function getEvent()
	{
		return new PluginEvent();
	}

	protected function get($key = null, $model = null, $id = null, $default = null)
	{
		return $default;
	}

	protected function set($name, $value, $model = null, $id = null): void
	{
	}
}
