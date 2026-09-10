export const SETTINGS_TEMPLATE_LINES = Object.freeze([
  'New Survey Completed!',
  'Title: <code>{{title}}</code>',
  'Respondent: <code>{{field:NAME.answer}}</code>',
]);

export const MASKED_SETTINGS_VALUES = Object.freeze({
  authToken: '123456789:bot-token-redacted',
  chatId: '-1001234567890',
});

export function buildMaskedSettingsValues() {
  return {
    ...MASKED_SETTINGS_VALUES,
    defaultText: SETTINGS_TEMPLATE_LINES.join('\n'),
  };
}
