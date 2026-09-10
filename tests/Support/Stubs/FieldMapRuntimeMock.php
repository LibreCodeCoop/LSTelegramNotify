<?php

class FieldMapRuntimeMock
{
    public static $createFieldMapHandler;
}

function createFieldMap($survey, $style, $full, $flatten, $language): array
{
    if (is_callable(FieldMapRuntimeMock::$createFieldMapHandler)) {
        return call_user_func(FieldMapRuntimeMock::$createFieldMapHandler, $survey, $style, $full, $flatten, $language);
    }

    return [];
}
