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
