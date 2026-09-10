<?php

class Permission
{
    /** @var callable|null */
    public static $hasSurveyPermissionHandler;

    public static function model()
    {
        return new class {
            public function hasSurveyPermission($surveyId, $permission, $crud)
            {
                if (is_callable(Permission::$hasSurveyPermissionHandler)) {
                    return call_user_func(Permission::$hasSurveyPermissionHandler, $surveyId, $permission, $crud);
                }

                return true;
            }
        };
    }
}
