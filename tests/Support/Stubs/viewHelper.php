<?php

class viewHelper
{
    public static $getFieldTextHandler;
    public static $getFieldCodeHandler;

    public static function getFieldText($field, array $options = []): string
    {
        if (is_callable(self::$getFieldTextHandler)) {
            return call_user_func(self::$getFieldTextHandler, $field, $options);
        }

        return '';
    }

    public static function getFieldCode($field, array $options = []): string
    {
        if (is_callable(self::$getFieldCodeHandler)) {
            return call_user_func(self::$getFieldCodeHandler, $field, $options);
        }

        return '';
    }
}
