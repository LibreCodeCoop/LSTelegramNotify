<?php

class AppRuntimeMock
{
    public static $createAbsoluteUrlHandler;

    public static $pluginManager;

    public function createAbsoluteUrl($route, array $params = []): string
    {
        if (is_callable(self::$createAbsoluteUrlHandler)) {
            return call_user_func(self::$createAbsoluteUrlHandler, $route, $params);
        }

        $query = http_build_query($params);
        return 'https://example.test' . $route . ($query !== '' ? '?' . $query : '');
    }

    public function getPluginManager()
    {
        return self::$pluginManager ?? new class {
            public function dispatchEvent($event, $plugin = null): void
            {
            }
        };
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
