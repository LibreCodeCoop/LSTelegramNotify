<?php

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
