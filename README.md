# LimeSurvey LSTelegramNotify Plugin

The LimeSurvey `LSTelegramNotify` plugin sends survey completion notifications to Telegram.

Useful improvements now available in this plugin include:

- global/per-survey `Enable` switch for notifications
- a `Send test message` control in global and survey-specific settings to validate the saved bot token and chat destination without using survey responses
- HTML-safe rendering when using `ParseMode = HTML`
- Telegram Rich Messages for `ParseMode = HTML`, including rich blocks such as headings, tables, details and media
- plain `Text` mode without Telegram `parse_mode`
- extra URL placeholders for common survey/response actions
- mustache placeholders for survey question/answer/raw values

The image below is a locally generated preview of a rendered Telegram message using sample data:

<!-- Maintainers: screenshot automation for the documentation images lives in `scripts/screenshots/README.md`. CI validates both screenshots, including this preview image. -->
<img src="img/telegram_example.png" />

## Plugin Installation

- Copy the LSTelegramNotify folder to the Limesurvey "plugins" directory.
- Go to `LSTelegramNotify` folder
- Run `composer install --no-dev` inside of folder `LSTelegramNotify` (only the runtime dependency is needed to run the plugin)
- Activate the plugin at the Limesurvey plugin manager (requires proper user rights for accessing the feature at the Limesurvey admin interface).
- Configure the plugin at the settings page

## Configuration

1. Create a Telegram bot with [@BotFather](https://t.me/BotFather) and copy the bot token.
2. Add the bot to the Telegram group that should receive survey notifications.
3. Get the group chat ID. One option is to temporarily add [@RawDataBot](https://t.me/RawDataBot) to the group, read the `chat.id` value it reports, and remove the bot afterwards.
4. Open the LSTelegramNotify settings in LimeSurvey.
5. Set `Bot token` to the token created by BotFather and `Chat ID` to the destination group ID.
6. Enable `Send a custom message` if you want a Telegram message for each completed response. When enabled, choose the `Message format` and edit the `Message template`.
7. Enable the PDF and CSV options independently if you also want those files sent with each completed response.
8. Save the settings, then use `Send test message` to verify that the saved bot token and chat destination work before relying on survey notifications.

The README screenshots below show the current plugin settings and a rendered Telegram notification example. They are generated and validated automatically by the repository screenshot workflow.

## Development

The linters, static analysis and test tools are managed as isolated
[composer-bin](https://github.com/bamarni/composer-bin-plugin) sets. After
`composer install`, pull them with:

```
composer tools:install
```

You can then run `composer test:unit`, `composer psalm` and `composer cs:check`.

## Available placeholders

### Metadata placeholders

These placeholders work in both legacy and mustache syntax:

- `{title}` / `{{title}}`
- `{surveyId}` / `{{surveyId}}`
- `{responseId}` / `{{responseId}}`
- `{urlPDF}` / `{{urlPDF}}`
- `{urlSurvey}` / `{{urlSurvey}}`
- `{urlDetails}` / `{{urlDetails}}`
- `{urlEdit}` / `{{urlEdit}}`
- `{urlExport}` / `{{urlExport}}`
- `{urlAttachments}` / `{{urlAttachments}}`

When `Message format` is `HTML`, the rendered template is sent through Telegram's `sendRichMessage` API using Rich HTML. This enables richer blocks such as headings, tables, `<details>` and media such as `<img src="https://example.test/photo.jpg" />`. All placeholder values are escaped before rendering so survey content cannot break the markup.

### Survey field placeholders

Use field codes in mustache syntax to inject survey questions and responses:

- `{{field:FIELD_CODE.question}}`
- `{{field:FIELD_CODE.answer}}`
- `{{field:FIELD_CODE.raw}}`

Shorthand aliases are also available:

- `{{FIELD_CODE}}`
- `{{FIELD_CODE_answer}}`
- `{{answer_FIELD_CODE}}`
- `{{raw_FIELD_CODE}}`

The global plugin settings screen documents the available placeholders directly in the UI:

<!-- Maintainers: CI validates this settings screenshot too. See `scripts/screenshots/README.md` for the local regeneration flow and disposable LimeSurvey stack. -->
<img src="img/settings.png" />

### Test message

The global plugin settings and each survey-specific settings page include a `Send test message` control. Save the settings first, then use the control to send a fixed plain-text message to the configured Telegram chat.

The test does not render the survey template and does not read or transmit survey responses. In survey settings it uses the effective survey configuration, including fallback to saved global `AuthToken` and `ChatId` values.

### Custom settings by survey
You can add custom settings by survey to send the messages to other groups, customize the text and change other settings.

- Go to survey settings
- GO to `Simple plugins`
- Define your custom settings at `Settings for plugin LSTelegramNotify `
