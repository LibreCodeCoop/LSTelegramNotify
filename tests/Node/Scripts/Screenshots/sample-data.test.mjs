import assert from 'node:assert/strict';
import test from 'node:test';
import { buildMaskedSettingsValues, MASKED_SETTINGS_VALUES, SETTINGS_TEMPLATE_LINES } from '../../../../scripts/screenshots/sample-data.mjs';

test('buildMaskedSettingsValues returns masked credentials and sample template text', () => {
  const sampleValues = buildMaskedSettingsValues();

  assert.equal(sampleValues.authToken, MASKED_SETTINGS_VALUES.authToken);
  assert.equal(sampleValues.chatId, MASKED_SETTINGS_VALUES.chatId);
  assert.equal(sampleValues.defaultText, SETTINGS_TEMPLATE_LINES.join('\n'));
});
