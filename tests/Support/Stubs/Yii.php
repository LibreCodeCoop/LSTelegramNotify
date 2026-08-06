<?php

class LSYii_ClientScript
{
    public const POS_POSTSCRIPT = 5;
}

class Yii
{
    public static array $registeredScripts = [];

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

            public function getPluginManager()
            {
                return \AppRuntimeMock::$pluginManager ?? new class {
                    public function dispatchEvent($event, $plugin = null): void
                    {
                    }
                };
            }

            public function getClientScript()
            {
                return new class {
                    public function registerScript(string $id, string $script, int $position = 0): void
                    {
                        Yii::$registeredScripts[$id] = [
                            'script' => $script,
                            'position' => $position,
                        ];
                    }
                };
            }
        };
    }
}
