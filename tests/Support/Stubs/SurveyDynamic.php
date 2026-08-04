<?php

class SurveyDynamic
{
    public static $findByPkHandler;
    public static $getMaxIdHandler;

    public static function model($surveyId)
    {
        return new class ($surveyId) {
            private $surveyId;

            public function __construct($surveyId)
            {
                $this->surveyId = $surveyId;
            }

            public function findByPk($responseId)
            {
                if (is_callable(SurveyDynamic::$findByPkHandler)) {
                    return call_user_func(SurveyDynamic::$findByPkHandler, $this->surveyId, $responseId);
                }

                return null;
            }

            public function getMaxId()
            {
                if (is_callable(SurveyDynamic::$getMaxIdHandler)) {
                    return call_user_func(SurveyDynamic::$getMaxIdHandler, $this->surveyId);
                }

                return null;
            }
        };
    }
}
