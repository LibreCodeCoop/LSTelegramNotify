import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildTelegramPreviewHtml,
  inlinePreviewStyles,
  TELEGRAM_PREVIEW_STYLE_TOKEN,
} from '../../../../scripts/screenshots/preview-template.mjs';

test('inlinePreviewStyles injects CSS into the preview template placeholder', () => {
  const html = inlinePreviewStyles(`<style>${TELEGRAM_PREVIEW_STYLE_TOKEN}</style>`, '.preview-shell { width: 920px; }');

  assert.match(html, /width: 920px/);
  assert.doesNotMatch(html, new RegExp(TELEGRAM_PREVIEW_STYLE_TOKEN));
});

test('buildTelegramPreviewHtml includes the preview heading and sample survey data', async () => {
  const html = await buildTelegramPreviewHtml();

  assert.match(html, /Telegram message preview/);
  assert.match(html, /Customer onboarding/);
  assert.match(html, /Ada Lovelace/);
  assert.match(html, /https:\/\/example\.test\/pdf/);
  assert.match(html, /\.preview-shell/);
});
