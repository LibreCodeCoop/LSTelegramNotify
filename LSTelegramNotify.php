<?php

require_once 'InputFile.php';
require_once 'Telegram.php';

/**
 * Class LSTelegramNotify
 */
class LSTelegramNotify extends PluginBase
{
    /**
     * @var string
     */
    static protected $description = 'LSTelegramNotify Plugin';

    /**
     * @var string
     */
    static protected $name = 'LSTelegramNotify';

    /**
     * @var string
     */
    protected $storage = 'DbStorage';

    /**
     * @var string[][]
     */
    protected $settings = [
        'AuthToken' => [
            'type' => 'string',
            'label' => 'Auth Token',
            'help' => 'Bot API auth token, you can get one at <a href="https://t.me/BotFather" target="_blank">BotFather</a>.',
        ],
        'ChatId' => [
            'type' => 'string',
            'label' => 'Chat id',
            'help' => 'The ID of group that will receive the notification messages. You can add the bot <a href="https://t.me/RawDataBot" target="_blank">RawDataBot</a> to your group, get the chat_id and after remove this bot from group.',
        ],
        'ParseMode' => [
            'type' => 'select',
            'label' => 'Parse mode',
            'options' => array('HTML' => 'HTML', 'Markdown'  => 'Markdown', 'MarkdownV2' => 'MarkdownV2'),
            'help' => 'As the Telegram bot API <a href="https://core.telegram.org/bots/api#formatting-options" target="_blank">formatting options</a>.',
            'default' => 'HTML',
        ],
        'SendPdf' => [
            'type' => 'checkbox',
            'label' => 'Check to send the answer as PDF file',
        ],
        'SendCsv' => [
            'type' => 'checkbox',
            'label' => 'Check to send all answers as CSV file',
        ],
        'SendMessage' => [
            'type' => 'checkbox',
            'label' => 'Check to send a text message using the default text template',
        ],
        'DefaultText' => [
            'type' => 'text',
            'label' => 'Default Text',
            'default' =>
                "New Survey Completed!\n" .
                "Title: <code>{title}</code>\n" .
                "SurveyId: <code>{surveyId}</code>\n" .
                "ResponseId: <code>{responseId}</code>\n" .
                "PDF: <a href=\"{urlPDF}\">here</a>"
        ],
    ];

    /**
     * @return void
     */
    public function init()
    {
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');
    }

    /**
     * @return void
     */
    public function afterSurveyComplete()
    {
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $responseId = $event->get('responseId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $chatId = $this->get(
            'ChatId',
            'Survey',
            $surveyId, // Survey
            $this->get('ChatId') // Global
        );
        $telegram = new Telegram($this->get(
            'AuthToken',
            'Survey',
            $surveyId, // Survey
            $this->get('AuthToken') // Global
        ));
        $this->sendMessage($surveyId, $responseId, $chatId, $telegram, $oSurvey->getLocalizedTitle());
        $this->sendPdf($surveyId, $responseId, $chatId, $telegram);
        $this->sendCsv($surveyId, $responseId, $chatId, $telegram);
    }

    /**
     * Send the message on Telegram
     *
     * @param $surveyId
     * @param $text
     */
    public function sendMessage($surveyId, $responseId, $chatId, Telegram $telegram, $title)
    {
        $sendMessage = $this->get(
            'SendMessage',
            'Survey',
            $surveyId, // Survey
            $this->get('SendMessage') // Global
        );
        if (!$sendMessage) {
            return;
        }
        $text = preg_replace(
            [
                '/\{surveyId\}/',
                '/\{responseId\}/',
                '/\{urlPDF\}/',
                '/\{title\}/',
            ],
            [
                $surveyId,
                $responseId,
                App()->createAbsoluteUrl(
                    '/admin/responses/sa/viewquexmlpdf',
                    [
                        'surveyid' => $surveyId,
                        'id' => $responseId
                    ]
                ),
                $title,
            ],
            $this->get(
                'DefaultText',
                'Survey',
                $surveyId, // Survey
                $this->get('DefaultText') // Global
            )
        );
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'parse_mode' => $this->get(
                'ParseMode',
                'Survey',
                $surveyId, // Survey
                $this->get('ParseMode') // Global
            ),
            'text' => $text
        ]);
    }

    private function sendPdf($surveyId, $responseId, $chatId, Telegram $telegram): void
    {
        $sendPdf = $this->get(
            'SendPdf',
            'Survey',
            $surveyId, // Survey
            $this->get('SendPdf') // Global
        );
        if (!$sendPdf) {
            return;
        }
        $pdfPath = $this->getPdfPath($surveyId, $responseId);
        $inputFile = new InputFile($pdfPath, "$surveyId-$responseId.pdf");
        $telegram->sendDocument([
            'chat_id' => $chatId,
            'document' => $inputFile,
        ]);
        unlink($pdfPath);
    }

    private function sendCsv($surveyId, $responseId, $chatId, Telegram $telegram): void
    {
        $sendCsv = $this->get(
            'SendCsv',
            'Survey',
            $surveyId, // Survey
            $this->get('SendCsv') // Global
        );
        if (!$sendCsv) {
            return;
        }
        $pdfPath = $this->getCsv($surveyId, $responseId);
        $inputFile = new InputFile($pdfPath, "$surveyId-$responseId.csv");
        $telegram->sendDocument([
            'chat_id' => $chatId,
            'document' => $inputFile,
        ]);
        unlink($pdfPath);
    }

    private function getPdfPath($surveyId, $responseId): string
    {
        Yii::import("application.libraries.admin.quexmlpdf", true);
        $oSurvey = Survey::model()->findByPk($surveyId);
        $quexmlpdf = new quexmlpdf();
        set_time_limit(120);
        App()->loadHelper('export');
        $quexml = quexml_export($surveyId, current($oSurvey->allLanguages), $responseId);
        $quexmlpdf->create($quexmlpdf->createqueXML($quexml));

        $tempnam = tempnam(sys_get_temp_dir(), 'pdf_');

        $quexmlpdf->Output($tempnam, 'F');
        return $tempnam;
    }

    /**
     * @return void
     */
    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $event->set(
            "surveysettings.{$this->id}",
            [
                'name' => get_class($this),
                'settings' => [
                    'SettingsInfo' => [
                        'type' => 'info',
                        'content' => '<legend><small>Telegram settings</small></legend>'
                    ],
                    'AuthToken' => [
                        'type' => 'string',
                        'label' => $this->settings['AuthToken']['help'],
                        'help' => $this->settings['AuthToken']['help'],
                        'current' => $this->get(
                            'AuthToken',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('AuthToken') // Global
                        ),
                    ],
                    'ChatId' => [
                        'type' => 'string',
                        'label' => 'Chat id',
                        'help' => $this->settings['ChatId']['help'],
                        'current' => $this->get(
                            'ChatId',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('ChatId') // Global
                        ),
                    ],
                    'ParseMode' => [
                        'type' => $this->settings['ParseMode']['type'],
                        'label' => $this->settings['ParseMode']['label'],
                        'options' => $this->settings['ParseMode']['options'],
                        'help' => $this->settings['ParseMode']['help'],
                        'default' => $this->settings['ParseMode']['default'],
                        'current' => $this->get(
                            'ParseMode',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'ParseMode',
                                null,
                                null,
                                $this->settings['ParseMode']['default']
                            ) // Global
                        ),
                    ],
                    'SendPdf' => [
                        'type' => $this->settings['SendPdf']['type'],
                        'label' => $this->settings['SendPdf']['label'],
                        'current' => $this->get(
                            'SendPdf',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendPdf',
                                null,
                                null,
                                $this->settings['SendPdf']['default']
                            ) // Global
                        ),
                    ],
                    'SendCsv' => [
                        'type' => $this->settings['SendCsv']['type'],
                        'label' => $this->settings['SendCsv']['label'],
                        'current' => $this->get(
                            'SendCsv',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendCsv',
                                null,
                                null,
                                $this->settings['SendCsv']['default']
                            ) // Global
                        ),
                    ],
                    'SendMessage' => [
                        'type' => $this->settings['SendMessage']['type'],
                        'label' => $this->settings['SendMessage']['label'],
                        'current' => $this->get(
                            'SendMessage',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendMessage',
                                null,
                                null,
                                $this->settings['SendMessage']['default']
                            ) // Global
                        ),
                    ],
                    'DefaultText' => [
                        'type' => 'text',
                        'label' => 'Default Text',
                        'current' => $this->get(
                            'DefaultText',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'DefaultText',
                                null,
                                null,
                                $this->settings['DefaultText']['default']
                            ) // Global
                        ),
                    ]
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function newSurveySettings()
    {
        $event = $this->getEvent();

        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }

    /**
     * Save file to CSV
     *
     * @return string File path
     */
    private function getCsv(): string
    {
        Yii::import('application.helpers.admin.export.FormattingOptions', true);
        Yii::import('application.helpers.admin.exportresults_helper', true);
        $survey = Survey::model()->findByPk($this->getEvent()->get('surveyId'));
        if (!($maxId = SurveyDynamic::model($this->getEvent()->get('surveyId'))->getMaxId())) {
            throw new Exception('No Data, could not get max id.', 1);
        }
        $oFormattingOptions = new FormattingOptions();
        $oFormattingOptions->responseMinRecord = 1;
        $oFormattingOptions->responseMaxRecord = $maxId;
        $aFields = array_keys(createFieldMap($survey, 'full', true, false, $survey->language));
        $aTokenFields = array('tid','participant_id','firstname','lastname','email','emailstatus','language','blacklisted','sent','remindersent','remindercount','completed','usesleft','validfrom','validuntil','mpid');
        $oFormattingOptions->selectedColumns = array_merge($aFields,$aTokenFields, array_keys($survey->tokenAttributes));
        $oFormattingOptions->responseCompletionState = 'all';
        $oFormattingOptions->headingFormat = 'full';
        $oFormattingOptions->answerFormat = 'long';
        $oFormattingOptions->csvFieldSeparator = ',';
        $oFormattingOptions->output = 'file';
        $oExport = new ExportSurveyResultsService();
        $tempFile = $oExport->exportResponses($this->getEvent()->get('surveyId'), $survey->language, 'csv', $oFormattingOptions, '');
        return $tempFile;
    }
}
