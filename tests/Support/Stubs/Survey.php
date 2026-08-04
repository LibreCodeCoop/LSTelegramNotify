<?php

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
