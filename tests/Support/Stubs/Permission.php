<?php

class Permission
{
    /** @var callable|null */
    public static $hasSurveyPermissionHandler;

    /** @var callable|null */
    public static $hasGlobalPermissionHandler;

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

            public function hasGlobalPermission($permission, $crud = null)
            {
                if (is_callable(Permission::$hasGlobalPermissionHandler)) {
                    return call_user_func(Permission::$hasGlobalPermissionHandler, $permission, $crud);
                }

                return true;
            }
        };
    }
}
