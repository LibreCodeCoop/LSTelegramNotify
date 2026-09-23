<?php

namespace LibreCodeCoop\LSTelegramNotify\Plugin;

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldPlaceholderCatalogProvider;
use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;
use LibreCodeCoop\LSTelegramNotify\Telegram\TelegramTestMessageSender;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class LSTelegramNotifyPlugin extends \PluginBase
{
	/** @var string[]|null */
	public $allowedPublicMethods = ['saveSurveyPluginSettings', 'sendTestMessage'];

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
	protected $storage = 'LimeSurvey\\PluginManager\\DbStorage';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected $settings = [
		'Enable' => [
			'type' => 'checkbox',
			'label' => 'Enable Telegram notifications',
			'default' => true,
		],
		'AuthToken' => [
			'type' => 'string',
			'label' => 'Bot token',
			'help' => 'Telegram Bot API token. Create or manage one with <a href="https://t.me/BotFather" target="_blank">BotFather</a>.',
		],
		'ChatId' => [
			'type' => 'string',
			'label' => 'Chat ID',
			'help' => 'The ID of the Telegram group that will receive notifications. You can temporarily add <a href="https://t.me/RawDataBot" target="_blank">RawDataBot</a> to the group to find its chat ID, then remove the bot.',
		],
		'TestMessage' => [
			'type' => 'info',
			'content' => '',
		],
		'SendMessage' => [
			'type' => 'checkbox',
			'label' => 'Send a custom message',
			'help' => 'Send a Telegram text message when a survey response is completed. Enable this to choose the message format and edit the template below.',
			'default' => false,
		],
		'ParseMode' => [
			'type' => 'select',
			'label' => 'Message format',
			'options' => array('HTML' => 'HTML', 'Markdown'  => 'Markdown', 'MarkdownV2' => 'MarkdownV2', 'Text' => 'Text'),
			'help' => 'Formatting used for the custom Telegram message. See the <a href="https://core.telegram.org/bots/api#formatting-options" target="_blank">Telegram formatting options</a>.',
			'default' => 'HTML',
		],
		'DefaultText' => [
			'type' => 'text',
			'label' => 'Message template',
			'default' =>
				"New Survey Completed!\n" .
				"Title: <code>{title}</code>\n" .
				"SurveyId: <code>{surveyId}</code>\n" .
				"ResponseId: <code>{responseId}</code>\n" .
				"PDF: <a href=\"{urlPDF}\">here</a>"
		],
		'SendPdf' => [
			'type' => 'checkbox',
			'label' => 'Send the response as a PDF file',
			'default' => false,
		],
		'SendCsv' => [
			'type' => 'checkbox',
			'label' => 'Send all survey responses as a CSV file',
			'default' => false,
		],
	];

	/**
	 * @return void
	 */
	public function init(): void
	{
		$this->settings['DefaultText']['help'] = $this->createDefaultTextHelpBuilder()->build();
		$this->settings['TestMessage'] = $this->createTestMessageUiBuilder()->buildSetting();
		$this->registerTestMessageScript();
		$this->registerMessageSettingsVisibilityScript();
		$this->subscribe('newSurveySettings');
		$this->subscribe('afterSurveyComplete');
		$this->subscribe('beforeSurveySettings');
	}

	/**
	 * @return void
	 */
	public function afterSurveyComplete(): void
	{
		$event = $this->getEvent();
		$surveyId = $event->get('surveyId');

		if (!$this->isNotificationEnabled((int) $surveyId)) {
			return;
		}

		$responseId = $event->get('responseId');
		$oSurvey = \Survey::model()->findByPk($surveyId);

		if ($oSurvey === null) {
			return;
		}

		$telegramSettings = $this->getTelegramConnectionSettings((int) $surveyId);
		$chatId = $telegramSettings['chatId'];
		$telegram = $this->createTelegramApi($telegramSettings['authToken']);
		$this->sendMessage($surveyId, $responseId, $chatId, $telegram, $oSurvey->getLocalizedTitle());
		$this->sendPdf($surveyId, $responseId, $chatId, $telegram);
		$this->sendCsv($surveyId, $responseId, $chatId, $telegram);
	}

	public function sendMessage(int $surveyId, int $responseId, string $chatId, Api $telegram, string $title): void
	{
		$sendMessage = $this->get(
			'SendMessage',
			'Survey',
			$surveyId,
			$this->get('SendMessage')
		);
		if (!$sendMessage) {
			return;
		}

		$parseMode = (string) $this->get(
			'ParseMode',
			'Survey',
			$surveyId,
			$this->get('ParseMode')
		);

		$text = $this->renderMessageTemplate(
			$this->get(
				'DefaultText',
				'Survey',
				$surveyId,
				$this->get('DefaultText')
			),
			$surveyId,
			$responseId,
			$title,
			$parseMode
		);

		if (strcasecmp($parseMode, 'HTML') === 0) {
			$richMessage = json_encode(
				['html' => $text],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);

			if ($richMessage === false) {
				throw new \RuntimeException('Could not encode Telegram rich message.');
			}

			$telegram->post('sendRichMessage', [
				'chat_id' => $chatId,
				'rich_message' => $richMessage,
			]);

			return;
		}

		$request = [
			'chat_id' => $chatId,
			'text' => $text
		];

		if (!$this->isPlainTextParseMode($parseMode)) {
			$request['parse_mode'] = $parseMode;
		}

		$telegram->sendMessage($request);
	}

	protected function renderMessageTemplate(string $template, int $surveyId, int $responseId, string $title, string $parseMode = ''): string
	{
		$placeholders = $this->getBaseTemplatePlaceholders($surveyId, $responseId, $title);
		$fieldValues = $this->getTemplateFieldValues($surveyId, $responseId);

		if ($this->shouldEscapeTemplateValues($parseMode)) {
			$placeholders = $this->escapeTemplatePlaceholders($placeholders);
			$fieldValues = $this->escapeTemplateFieldValues($fieldValues);
		}

		return $this->createMessageTemplateRenderer()->render(
			$template,
			$placeholders,
			$fieldValues
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function getBaseTemplatePlaceholders(int $surveyId, int $responseId, string $title): array
	{
		return [
			'surveyId' => (string) $surveyId,
			'responseId' => (string) $responseId,
			'urlPDF' => \App()->createAbsoluteUrl(
				'/admin/responses/sa/viewquexmlpdf',
				[
					'surveyid' => $surveyId,
					'id' => $responseId,
				]
			),
			'urlSurvey' => \App()->createAbsoluteUrl(
				'/surveyAdministration/view',
				[
					'surveyid' => $surveyId,
				]
			),
			'urlDetails' => \App()->createAbsoluteUrl(
				'/responses/view',
				[
					'surveyId' => $surveyId,
					'id' => $responseId,
				]
			),
			'urlEdit' => \App()->createAbsoluteUrl(
				"/admin/dataentry/sa/editdata/subaction/edit/surveyId/$surveyId/id/$responseId/browseLang"
			),
			'urlExport' => \App()->createAbsoluteUrl(
				"/admin/export/sa/exportresults/surveyid/$surveyId/id/$responseId"
			),
			'urlAttachments' => \App()->createAbsoluteUrl(
				'/responses/downloadfiles',
				[
					'surveyId' => $surveyId,
					'responseIds' => $responseId,
				]
			),
			'title' => $title,
		];
	}

	/**
	 * @return array{authToken: string, chatId: string}
	 */
	protected function getTelegramConnectionSettings(?int $surveyId = null): array
	{
		$authToken = (string) $this->get('AuthToken');
		$chatId = (string) $this->get('ChatId');

		if ($surveyId !== null) {
			$authToken = (string) $this->get('AuthToken', 'Survey', $surveyId, $authToken);
			$chatId = (string) $this->get('ChatId', 'Survey', $surveyId, $chatId);
		}

		return [
			'authToken' => trim($authToken),
			'chatId' => trim($chatId),
		];
	}

	protected function isNotificationEnabled(int $surveyId): bool
	{
		return (bool) $this->get(
			'Enable',
			'Survey',
			$surveyId,
			$this->get(
				'Enable',
				null,
				null,
				$this->settings['Enable']['default']
			)
		);
	}

	protected function isPlainTextParseMode(string $parseMode): bool
	{
		return strcasecmp($parseMode, 'Text') === 0;
	}

	protected function shouldEscapeTemplateValues(string $parseMode): bool
	{
		return strcasecmp($parseMode, 'HTML') === 0;
	}

	/**
	 * @param array<string, string> $placeholders
	 *
	 * @return array<string, string>
	 */
	protected function escapeTemplatePlaceholders(array $placeholders): array
	{
		return array_map(function (string $value): string {
			return $this->escapeTemplateValue($value);
		}, $placeholders);
	}

	/**
	 * @param array<string, array<string, string>> $fieldValues
	 *
	 * @return array<string, array<string, string>>
	 */
	protected function escapeTemplateFieldValues(array $fieldValues): array
	{
		foreach ($fieldValues as $fieldCode => $fieldValue) {
			foreach ($fieldValue as $property => $value) {
				$fieldValues[$fieldCode][$property] = $this->escapeTemplateValue($value);
			}
		}

		return $fieldValues;
	}

	protected function escapeTemplateValue(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES);
	}

	protected function createDefaultTextHelpBuilder(): DefaultTextHelpBuilder
	{
		return new DefaultTextHelpBuilder($this->createSurveyFieldPlaceholderCatalogProvider());
	}

	protected function createSurveyFieldPlaceholderCatalogProvider(): SurveyFieldPlaceholderCatalogProvider
	{
		return new SurveyFieldPlaceholderCatalogProvider();
	}

	protected function createMessageTemplateRenderer(): MessageTemplateRenderer
	{
		return new MessageTemplateRenderer();
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	protected function getTemplateFieldValues(int $surveyId, int $responseId): array
	{
		return $this->createSurveyFieldValueProvider()->getFieldValues($surveyId, $responseId);
	}

	protected function createSurveyFieldValueProvider(): SurveyFieldValueProvider
	{
		return new SurveyFieldValueProvider();
	}

	protected function createTestMessageUiBuilder(): TestMessageUiBuilder
	{
		return new TestMessageUiBuilder();
	}

	protected function createMessageSettingsUiBuilder(): MessageSettingsUiBuilder
	{
		return new MessageSettingsUiBuilder();
	}

	protected function createTelegramApi(string $authToken): Api
	{
		return new Api($authToken);
	}

	protected function createTelegramTestMessageSender(): TelegramTestMessageSender
	{
		return new TelegramTestMessageSender();
	}

	private function sendPdf(int $surveyId, int $responseId, string $chatId, Api $telegram): void
	{
		$sendPdf = $this->get(
			'SendPdf',
			'Survey',
			$surveyId,
			$this->get('SendPdf')
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

	private function sendCsv(int $surveyId, int $responseId, string $chatId, Api $telegram): void
	{
		$sendCsv = $this->get(
			'SendCsv',
			'Survey',
			$surveyId,
			$this->get('SendCsv')
		);
		if (!$sendCsv) {
			return;
		}
		$csvPath = $this->getCsv($surveyId);
		$inputFile = new InputFile($csvPath, "$surveyId-$responseId.csv");
		$telegram->sendDocument([
			'chat_id' => $chatId,
			'document' => $inputFile,
		]);
		unlink($csvPath);
	}

	private function getPdfPath(int $surveyId, int $responseId): string
	{
		\Yii::import('application.libraries.admin.quexmlpdf', true);
		$oSurvey = \Survey::model()->findByPk($surveyId);

		if ($oSurvey === null) {
			throw new \RuntimeException('Survey not found.');
		}

		$quexmlpdf = new \quexmlpdf();
		set_time_limit(120);
		\App()->loadHelper('export');
		$language = current($oSurvey->allLanguages);

		if ($language === false || !is_string($language) || $language === '') {
			$language = $oSurvey->language;
		}

		$quexml = \quexml_export($surveyId, $language, $responseId);
		$quexmlpdf->create($quexmlpdf->createqueXML($quexml));

		$tempnam = tempnam(sys_get_temp_dir(), 'pdf_');

		if ($tempnam === false) {
			throw new \RuntimeException('Could not create temporary PDF file.');
		}

		$quexmlpdf->Output($tempnam, 'F');
		return $tempnam;
	}

	/**
	 * @return void
	 */
	public function beforeSurveySettings(): void
	{
		$event = $this->getEvent();
		$surveyId = (int) $event->get('survey');
		$defaultTextHelp = $this->createDefaultTextHelpBuilder()->build($surveyId);
		$this->registerSurveyPluginSettingsSaveWorkaround();
		$event->set(
			"surveysettings.{$this->id}",
			[
				'name' => get_class($this),
				'settings' => [
					'Enable' => [
						'type' => $this->settings['Enable']['type'],
						'label' => $this->settings['Enable']['label'],
						'default' => $this->settings['Enable']['default'],
						'current' => $this->get(
							'Enable',
							'Survey',
							$surveyId,
							$this->get(
								'Enable',
								null,
								null,
								$this->settings['Enable']['default']
							)
						),
					],
					'SettingsInfo' => [
						'type' => 'info',
						'content' => '<legend><small>Telegram settings</small></legend>'
					],
					'AuthToken' => [
						'type' => 'string',
						'label' => $this->settings['AuthToken']['label'],
						'help' => $this->settings['AuthToken']['help'],
						'current' => $this->get(
							'AuthToken',
							'Survey',
							$surveyId,
							$this->get('AuthToken')
						),
					],
					'ChatId' => [
						'type' => 'string',
						'label' => $this->settings['ChatId']['label'],
						'help' => $this->settings['ChatId']['help'],
						'current' => $this->get(
							'ChatId',
							'Survey',
							$surveyId,
							$this->get('ChatId')
						),
					],
					'TestMessage' => $this->createTestMessageUiBuilder()->buildSetting($surveyId),
					'SendMessage' => [
						'type' => $this->settings['SendMessage']['type'],
						'label' => $this->settings['SendMessage']['label'],
						'help' => $this->settings['SendMessage']['help'],
						'current' => $this->get(
							'SendMessage',
							'Survey',
							$surveyId,
							$this->get(
								'SendMessage',
								null,
								null,
								$this->settings['SendMessage']['default']
							)
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
							$surveyId,
							$this->get(
								'ParseMode',
								null,
								null,
								$this->settings['ParseMode']['default']
							)
						),
					],
					'DefaultText' => [
						'type' => 'text',
						'label' => $this->settings['DefaultText']['label'],
						'help' => $defaultTextHelp,
						'current' => $this->get(
							'DefaultText',
							'Survey',
							$surveyId,
							$this->get(
								'DefaultText',
								null,
								null,
								$this->settings['DefaultText']['default']
							)
						),
					],
					'SendPdf' => [
						'type' => $this->settings['SendPdf']['type'],
						'label' => $this->settings['SendPdf']['label'],
						'current' => $this->get(
							'SendPdf',
							'Survey',
							$surveyId,
							$this->get(
								'SendPdf',
								null,
								null,
								$this->settings['SendPdf']['default']
							)
						),
					],
					'SendCsv' => [
						'type' => $this->settings['SendCsv']['type'],
						'label' => $this->settings['SendCsv']['label'],
						'current' => $this->get(
							'SendCsv',
							'Survey',
							$surveyId,
							$this->get(
								'SendCsv',
								null,
								null,
								$this->settings['SendCsv']['default']
							)
						),
					],

				]
			]
		);
	}

	public function sendTestMessage($request): string
	{
		$surveyId = $request->getPost('surveyId');
		$normalizedSurveyId = null;

		if ($surveyId !== null && $surveyId !== '') {
			if (!is_numeric($surveyId)) {
				return $this->buildJsonResponse(false, 'Invalid survey id.', 400);
			}

			$normalizedSurveyId = (int) $surveyId;

			if (!\Permission::model()->hasSurveyPermission($normalizedSurveyId, 'surveysettings', 'update')) {
				return $this->buildJsonResponse(
					false,
					'You do not have permission to test Telegram settings for this survey.',
					403
				);
			}
		} elseif (!\Permission::model()->hasGlobalPermission('settings', 'update')) {
			return $this->buildJsonResponse(
				false,
				'You do not have permission to test the global Telegram settings.',
				403
			);
		}

		$telegramSettings = $this->getTelegramConnectionSettings($normalizedSurveyId);

		if ($telegramSettings['authToken'] === '' || $telegramSettings['chatId'] === '') {
			return $this->buildJsonResponse(false, 'Auth Token and Chat id must be saved before testing.', 400);
		}

		try {
			$this->createTelegramTestMessageSender()->send(
				$this->createTelegramApi($telegramSettings['authToken']),
				$telegramSettings['chatId']
			);
		} catch (\Throwable $exception) {
			return $this->buildJsonResponse(false, $exception->getMessage(), 502);
		}

		return $this->buildJsonResponse(true, 'Test message sent successfully.');
	}

	public function saveSurveyPluginSettings($request): string
	{
		$surveyId = $request->getPost('sid', $request->getPost('surveyid'));

		if (!is_numeric($surveyId)) {
			return $this->buildSurveyPluginSettingsSaveResponse(false, 'Missing survey id.', 400);
		}

		if (!\Permission::model()->hasSurveyPermission((int) $surveyId, 'surveysettings', 'update')) {
			return $this->buildSurveyPluginSettingsSaveResponse(
				false,
				'You do not have permission to update the settings for this survey.',
				403
			);
		}

		$pluginSettings = $request->getPost('plugin', []);

		if (!is_array($pluginSettings)) {
			$pluginSettings = [];
		}

		try {
			foreach ($pluginSettings as $pluginName => $settings) {
				if (!is_array($settings)) {
					continue;
				}

				$this->dispatchSurveyPluginSettings(
					(string) $pluginName,
					$settings,
					(int) $surveyId
				);
			}
		} catch (\Throwable $exception) {
			return $this->buildSurveyPluginSettingsSaveResponse(
				false,
				$exception->getMessage(),
				500
			);
		}

		return $this->buildSurveyPluginSettingsSaveResponse(true);
	}

	/**
	 * @return void
	 */
	public function newSurveySettings(): void
	{
		$event = $this->getEvent();
		$settings = $event->get('settings');
		$surveyId = $event->get('survey');

		if (!is_iterable($settings)) {
			return;
		}

		if (!is_int($surveyId) && !is_string($surveyId) && !is_float($surveyId)) {
			return;
		}

		foreach ($settings as $name => $value) {
			$this->set($name, $value, 'Survey', (int) $surveyId);
		}
	}

	protected function registerMessageSettingsVisibilityScript(): void
	{
		$script = $this->createMessageSettingsUiBuilder()->buildScript();

		if ($script === '') {
			return;
		}

		\Yii::app()->getClientScript()->registerScript(
			'ls-telegram-notify-message-settings-visibility',
			$script,
			\LSYii_ClientScript::POS_POSTSCRIPT
		);
	}

	protected function registerSurveyPluginSettingsSaveWorkaround(): void
	{
		$saveUrl = \App()->createAbsoluteUrl(
			'/admin/pluginhelper/sa/ajax',
			[
				'plugin' => static::$name,
				'method' => 'saveSurveyPluginSettings',
			]
		);
		$encodedSaveUrl = json_encode($saveUrl);

		if ($encodedSaveUrl === false) {
			return;
		}

		\Yii::app()->getClientScript()->registerScript(
			'ls-telegram-notify-survey-plugin-save-workaround',
			<<<JS
	var updateLSTelegramNotifySurveyPluginFormAction = function () {
	var form = $('#plugins');

	if (!form.length) {
		return;
	}

	form.attr('action', {$encodedSaveUrl});
};

updateLSTelegramNotifySurveyPluginFormAction();
$(document)
	.off('pjax:scriptcomplete.lsTelegramNotifySurveyPluginSaveWorkaround')
	.on('pjax:scriptcomplete.lsTelegramNotifySurveyPluginSaveWorkaround', updateLSTelegramNotifySurveyPluginFormAction);
JS,
			\LSYii_ClientScript::POS_POSTSCRIPT
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	protected function dispatchSurveyPluginSettings(string $pluginName, array $settings, int $surveyId): void
	{
		$event = new \PluginEvent('newSurveySettings');
		$event->set('settings', $settings);
		$event->set('survey', $surveyId);

		\Yii::app()->getPluginManager()->dispatchEvent($event, $pluginName);
	}

	protected function buildSurveyPluginSettingsSaveResponse(bool $success, string $message = '', int $statusCode = 200): string
	{
		return $this->buildJsonResponse($success, $message, $statusCode);
	}

	protected function buildJsonResponse(bool $success, string $message = '', int $statusCode = 200): string
	{
		http_response_code($statusCode);

		$response = ['success' => $success];

		if ($message !== '') {
			$response['message'] = $message;
		}

		$json = json_encode($response);

		if ($json === false) {
			return '{"success":false,"message":"Could not encode response."}';
		}

		return $json;
	}

	protected function registerTestMessageScript(): void
	{
		$endpointUrl = \App()->createAbsoluteUrl(
			'/admin/pluginhelper/sa/ajax',
			[
				'plugin' => static::$name,
				'method' => 'sendTestMessage',
			]
		);
		$script = $this->createTestMessageUiBuilder()->buildScript($endpointUrl);

		if ($script === '') {
			return;
		}

		\Yii::app()->getClientScript()->registerScript(
			'ls-telegram-notify-test-message',
			$script,
			\LSYii_ClientScript::POS_POSTSCRIPT
		);
	}

	private function getCsv(int $surveyId): string
	{
		\Yii::import('application.helpers.admin.export.FormattingOptions', true);
		\Yii::import('application.helpers.admin.exportresults_helper', true);
		$survey = \Survey::model()->findByPk($surveyId);

		if ($survey === null) {
			throw new \RuntimeException('Survey not found.');
		}

		$maxId = \SurveyDynamic::model($surveyId)->getMaxId();

		if ($maxId === null || $maxId < 1) {
			throw new \Exception('No Data, could not get max id.', 1);
		}

		$oFormattingOptions = new \FormattingOptions();
		$oFormattingOptions->responseMinRecord = 1;
		$oFormattingOptions->responseMaxRecord = $maxId;
		$aFields = array_keys(\createFieldMap($survey, 'full', true, false, $survey->language));
		$oFormattingOptions->selectedColumns = $aFields;

		if ($survey->hasTokensTable) {
			$aTokenFields = array('tid','participant_id','firstname','lastname','email','emailstatus','language','blacklisted','sent','remindersent','remindercount','completed','usesleft','validfrom','validuntil','mpid');
			$oFormattingOptions->selectedColumns = array_merge(
				$aFields,
				$aTokenFields,
				array_keys($survey->tokenAttributes)
			);
		}
		$oFormattingOptions->responseCompletionState = 'all';
		$oFormattingOptions->headingFormat = 'full';
		$oFormattingOptions->answerFormat = 'long';
		$oFormattingOptions->csvFieldSeparator = ',';
		$oFormattingOptions->output = 'file';
		$oExport = new \ExportSurveyResultsService();
		return $oExport->exportResponses($surveyId, $survey->language, 'csv', $oFormattingOptions, '');
	}
}
