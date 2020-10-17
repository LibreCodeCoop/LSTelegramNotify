<?php

/**
 * Class LSTelegramNotify
 */
class LSTelegramNotify extends PluginBase
{
    static protected $description = 'LSTelegramNotify Plugin';
    static protected $name = 'LSTelegramNotify';

    protected $storage = 'DbStorage';

    protected $settings = array(
        'AuthToken' => array(
            'type' => 'string',
            'label' => 'Auth Token'
        ),
        'ChatId' => array(
            'type' => 'string',
            'label' => 'chat id'
        ),
        'DefaultText' => array(
            'type' => 'text',
            'label' => 'Default Text',
            'default' =>
                "New Survey Completed!\n" .
                "SurveyId: {surveyId}\n" .
                "ResponseId: {responseId}\n" .
                "UrlPDF: {urlPDF}"
        ),
    );

    public function init()
    {
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');
    }

    public function afterSurveyComplete()
    {
        $event = $this->getEvent();
        $text  = preg_replace(
            [
                '/\{surveyId\}/',
                '/\{responseId\}/',
                '/\{urlPDF\}/'
            ],
            [
                $surveyId = $event->get('surveyId'),
                $responseId = $event->get('responseId'),
                App()->createAbsoluteUrl(
                    '/admin/responses/sa/viewquexmlpdf',
                    array(
                        'surveyid'=>$surveyId,
                        'id'=>$responseId
                    )
                )
            ],
            $this->get(
                'DefaultText', 'Survey', $surveyId, // Survey
                $this->get('DefaultText') // Global
            )
        );
        $this->sendMessage($surveyId, $text);
    }

    public function sendMessage($surveyId, $text)
    {
        $url = 'https://api.telegram.org/bot' .
                $this->get(
                    'AuthToken', 'Survey', $surveyId, // Survey
                    $this->get('AuthToken') // Global
                ) .
                '/sendMessage?chat_id=' .
                $this->get(
                    'ChatId', 'Survey', $surveyId, // Survey
                    $this->get('ChatId') // Global
                ) . 
                '&text=' . urlencode($text);
        file_get_contents($url);
    }

    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => array(
                    'SettingsInfo'=>array(
                        'type'=>'info',
                        'content'=>'<legend><small>Telegram settings</small></legend>'
                    ),
                    'AuthToken' => array(
                        'type' => 'string',
                        'label' => 'Auth Token',
                        'current' => $this->get(
                            'AuthToken', 'Survey', $event->get('survey'), // Survey
                            $this->get('AuthToken') // Global
                        ),
                    ),
                    'ChatId' => array(
                        'type' => 'string',
                        'label' => 'chat id',
                        'current' => $this->get(
                            'ChatId', 'Survey', $event->get('survey'), // Survey
                            $this->get('ChatId') // Global
                        ),
                    ),
                    'DefaultText' => array(
                        'type' => 'text',
                        'label' => 'Default Text',
                        'current' => $this->get(
                            'DefaultText', 'Survey', $event->get('survey'), // Survey
                            $this->get('DefaultText', null, null,
                                $this->settings['DefaultText']['default']
                            ) // Global
                        ),
                    )
                )
            ));

    }

    public function newSurveySettings()
    {
        $event = $this->getEvent();
        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }
}
