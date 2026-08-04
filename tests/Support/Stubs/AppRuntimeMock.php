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
