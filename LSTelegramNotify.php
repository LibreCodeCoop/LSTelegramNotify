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
                "ResponseId: {responseId}"
        ),
    );

    public function init()
    {
        $this->subscribe('afterSurveyComplete', 'telegramNotify');
    }

    /**
     * afterSurveyComplete
     */
    public function telegramNotify()
    {
        $event      = $this->getEvent();
        $text = preg_replace(
            [
                '/\{surveyId\}/',
                '/\{responseId\}/'
            ],
            [
                $surveyId = $event->get('surveyId'),
                $responseId = $event->get('responseId')
            ],
            $this->get('DefaultText')
        );
        $url = 'https://api.telegram.org/bot' .
                $this->get('AuthToken') .
                '/sendMessage?chat_id=' .
                $this->get('ChatId') . 
                '&text=' . urlencode($text);
        file_get_contents($url);
    }
}
