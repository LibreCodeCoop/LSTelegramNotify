<?php

namespace LibreCodeCoop\LSTelegramNotify\Plugin;

use LibreCodeCoop\LSTelegramNotify\Survey\SurveyFieldValueProvider;
use LibreCodeCoop\LSTelegramNotify\Template\MessageTemplateRenderer;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class LSTelegramNotifyPlugin extends \PluginBase
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
		'Enable' => [
			'type' => 'checkbox',
			'label' => 'Enable telegram notifications',
			'default' => true,
		],
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
			'options' => array('HTML' => 'HTML', 'Markdown'  => 'Markdown', 'MarkdownV2' => 'MarkdownV2', 'Text' => 'Text'),
			'help' => 'As the Telegram bot API <a href="https://core.telegram.org/bots/api#formatting-options" target="_blank">formatting options</a>.',
			'default' => 'HTML',
		],
		'SendPdf' => [
			'type' => 'checkbox',
			'label' => 'Check to send the answer as PDF file',
			'default' => false,
		],
		'SendCsv' => [
			'type' => 'checkbox',
			'label' => 'Check to send all answers as CSV file',
			'default' => false,
		],
		'SendMessage' => [
			'type' => 'checkbox',
			'label' => 'Check to send a text message using the default text template',
			'default' => false,
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
	public function init(): void
	{
		$this->settings['DefaultText']['help'] = $this->buildDefaultTextHelp();
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

		$chatId = $this->get(
			'ChatId',
			'Survey',
			$surveyId,
			$this->get('ChatId')
		);
		$telegram = new Api($this->get(
			'AuthToken',
			'Survey',
			$surveyId,
			$this->get('AuthToken')
		));
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

	protected function buildDefaultTextHelp(?int $surveyId = null): string
	{
		$sections = [
			'<p>Available placeholders for the Telegram message template:</p>',
			'<p><strong>Metadata placeholders</strong></p>',
			'<ul>' . $this->buildMetadataPlaceholderHelpItems() . '</ul>',
			'<p><strong>Survey field syntax</strong></p>',
			'<ul>' . $this->buildFieldSyntaxHelpItems() . '</ul>',
			'<p>When <code>ParseMode</code> is <code>HTML</code>, placeholder values are escaped automatically before rendering.</p>',
		];

		if ($surveyId === null) {
			$sections[] = '<p>Open the survey-specific plugin settings to see the field codes available for a particular survey.</p>';

			return implode("\n", $sections);
		}

		$fieldCatalog = $this->getSurveyFieldPlaceholderCatalog($surveyId);

		if ($fieldCatalog === []) {
			$sections[] = '<p>No field codes could be identified for this survey.</p>';

			return implode("\n", $sections);
		}

		$fieldItems = [];

		foreach ($fieldCatalog as $fieldCode => $label) {
			$fieldItems[] = sprintf(
				'<li><code>%s</code> &mdash; %s</li>',
				htmlspecialchars($fieldCode, ENT_QUOTES),
				htmlspecialchars($label, ENT_QUOTES)
			);
		}

		$sections[] = '<p><strong>Field codes available in this survey</strong></p>';
		$sections[] = '<ul>' . implode('', $fieldItems) . '</ul>';

		return implode("\n", $sections);
	}

	protected function buildMetadataPlaceholderHelpItems(): string
	{
		$placeholders = [
			['tokens' => ['{title}', '{{title}}'], 'description' => 'Survey title'],
			['tokens' => ['{surveyId}', '{{surveyId}}'], 'description' => 'Survey identifier'],
			['tokens' => ['{responseId}', '{{responseId}}'], 'description' => 'Response identifier'],
			['tokens' => ['{urlPDF}', '{{urlPDF}}'], 'description' => 'PDF download URL'],
			['tokens' => ['{urlSurvey}', '{{urlSurvey}}'], 'description' => 'Survey administration URL'],
			['tokens' => ['{urlDetails}', '{{urlDetails}}'], 'description' => 'Response details URL'],
			['tokens' => ['{urlEdit}', '{{urlEdit}}'], 'description' => 'Response edit URL'],
			['tokens' => ['{urlExport}', '{{urlExport}}'], 'description' => 'Response export URL'],
			['tokens' => ['{urlAttachments}', '{{urlAttachments}}'], 'description' => 'Response attachments URL'],
		];

		$items = [];

		foreach ($placeholders as $placeholder) {
			$items[] = sprintf(
				'<li>%s &mdash; %s</li>',
				$this->formatHelpCodeTokens($placeholder['tokens']),
				htmlspecialchars($placeholder['description'], ENT_QUOTES)
			);
		}

		return implode('', $items);
	}

	protected function buildFieldSyntaxHelpItems(): string
	{
		$placeholders = [
			['token' => '{{field:FIELD_CODE.question}}', 'description' => 'Question text for a survey field'],
			['token' => '{{field:FIELD_CODE.answer}}', 'description' => 'Formatted answer for a survey field'],
			['token' => '{{field:FIELD_CODE.raw}}', 'description' => 'Raw stored value for a survey field'],
			['token' => '{{FIELD_CODE}}', 'description' => 'Shortcut for the question text'],
			['token' => '{{FIELD_CODE_answer}}', 'description' => 'Shortcut for the formatted answer'],
			['token' => '{{answer_FIELD_CODE}}', 'description' => 'Alternative answer shortcut'],
			['token' => '{{raw_FIELD_CODE}}', 'description' => 'Shortcut for the raw stored value'],
		];

		$items = [];

		foreach ($placeholders as $placeholder) {
			$items[] = sprintf(
				'<li><code>%s</code> &mdash; %s</li>',
				htmlspecialchars($placeholder['token'], ENT_QUOTES),
				htmlspecialchars($placeholder['description'], ENT_QUOTES)
			);
		}

		return implode('', $items);
	}

	protected function formatHelpCodeTokens(array $tokens): string
	{
		$formatted = array_map(function (string $token): string {
			return '<code>' . htmlspecialchars($token, ENT_QUOTES) . '</code>';
		}, $tokens);

		return implode(' / ', $formatted);
	}

	/**
	 * @return array<string, string>
	 */
	protected function getSurveyFieldPlaceholderCatalog(int $surveyId): array
	{
		$survey = \Survey::model()->findByPk($surveyId);

		if ($survey === null) {
			return [];
		}

		\Yii::import('application.helpers.viewHelper', true);
		$fieldMap = \createFieldMap($survey, 'full', true, false, $survey->language);

		if (!is_array($fieldMap)) {
			return [];
		}

		$fieldCatalog = [];

		foreach ($fieldMap as $fieldName => $field) {
			$fieldCode = trim((string) \viewHelper::getFieldCode($field, ['separator' => ['[', ']']]));

			if ($fieldCode === '') {
				continue;
			}

			$label = trim((string) \viewHelper::getFieldText($field, [
				'flat' => true,
				'separator' => ['[', ']'],
				'afterquestion' => ' ',
			]));

			if ($label === '') {
				$label = (string) $fieldName;
			}

			if (!array_key_exists($fieldCode, $fieldCatalog)) {
				$fieldCatalog[$fieldCode] = $label;
			}
		}

		ksort($fieldCatalog, SORT_NATURAL | SORT_FLAG_CASE);

		return $fieldCatalog;
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
		$pdfPath = $this->getCsv($surveyId, $responseId);
		$inputFile = new InputFile($pdfPath, "$surveyId-$responseId.csv");
		$telegram->sendDocument([
			'chat_id' => $chatId,
			'document' => $inputFile,
		]);
		unlink($pdfPath);
	}

	private function getPdfPath(int $surveyId, int $responseId): string
	{
		\Yii::import('application.libraries.admin.quexmlpdf', true);
		$oSurvey = \Survey::model()->findByPk($surveyId);
		$quexmlpdf = new \quexmlpdf();
		set_time_limit(120);
		\App()->loadHelper('export');
		$quexml = \quexml_export($surveyId, current($oSurvey->allLanguages), $responseId);
		$quexmlpdf->create($quexmlpdf->createqueXML($quexml));

		$tempnam = tempnam(sys_get_temp_dir(), 'pdf_');

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
						'label' => 'Chat id',
						'help' => $this->settings['ChatId']['help'],
						'current' => $this->get(
							'ChatId',
							'Survey',
							$surveyId,
							$this->get('ChatId')
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
					'SendMessage' => [
						'type' => $this->settings['SendMessage']['type'],
						'label' => $this->settings['SendMessage']['label'],
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
					'DefaultText' => [
						'type' => 'text',
						'label' => 'Default Text',
						'help' => $this->buildDefaultTextHelp($surveyId),
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
					]
				]
			]
		);
	}

	/**
	 * @return void
	 */
	public function newSurveySettings(): void
	{
		$event = $this->getEvent();

		foreach ($event->get('settings') as $name => $value) {
			$this->set($name, $value, 'Survey', $event->get('survey'));
		}
	}

	private function getCsv(): string
	{
		\Yii::import('application.helpers.admin.export.FormattingOptions', true);
		\Yii::import('application.helpers.admin.exportresults_helper', true);
		$survey = \Survey::model()->findByPk($this->getEvent()->get('surveyId'));
		if (!(\SurveyDynamic::model($this->getEvent()->get('surveyId'))->getMaxId())) {
			throw new \Exception('No Data, could not get max id.', 1);
		}
		$maxId = \SurveyDynamic::model($this->getEvent()->get('surveyId'))->getMaxId();
		$oFormattingOptions = new \FormattingOptions();
		$oFormattingOptions->responseMinRecord = 1;
		$oFormattingOptions->responseMaxRecord = $maxId;
		$aFields = array_keys(\createFieldMap($survey, 'full', true, false, $survey->language));
		$aTokenFields = array('tid','participant_id','firstname','lastname','email','emailstatus','language','blacklisted','sent','remindersent','remindercount','completed','usesleft','validfrom','validuntil','mpid');
		$oFormattingOptions->selectedColumns = array_merge($aFields, $aTokenFields, array_keys($survey->tokenAttributes));
		$oFormattingOptions->responseCompletionState = 'all';
		$oFormattingOptions->headingFormat = 'full';
		$oFormattingOptions->answerFormat = 'long';
		$oFormattingOptions->csvFieldSeparator = ',';
		$oFormattingOptions->output = 'file';
		$oExport = new \ExportSurveyResultsService();
		return $oExport->exportResponses($this->getEvent()->get('surveyId'), $survey->language, 'csv', $oFormattingOptions, '');
	}
}
