import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildEmbeddedPreviewFontCss,
  buildFontDataUrl,
  buildFontFaceCss,
  buildTelegramPreviewHtml,
  inlinePreviewStyles,
  TELEGRAM_PREVIEW_STYLE_TOKEN,
  TELEGRAM_PREVIEW_MONO_FAMILY,
  TELEGRAM_PREVIEW_SANS_FAMILY,
} from '../../../../scripts/screenshots/preview-template.mjs';

test('buildFontDataUrl encodes a WOFF2 buffer as a data URI', () => {
  const result = buildFontDataUrl(Buffer.from('font-bytes'));

  assert.equal(result, 'data:font/woff2;base64,Zm9udC1ieXRlcw==');
});

test('buildFontFaceCss creates an embeddable @font-face rule', () => {
  const css = buildFontFaceCss({
    family: TELEGRAM_PREVIEW_SANS_FAMILY,
    weight: 700,
    style: 'normal',
    source: 'data:font/woff2;base64,AAAA',
  });

  assert.match(css, /@font-face/);
  assert.match(css, /TelegramPreviewSans/);
  assert.match(css, /font-weight: 700/);
  assert.match(css, /data:font\/woff2;base64,AAAA/);
});

test('inlinePreviewStyles injects CSS into the preview template placeholder', () => {
  const html = inlinePreviewStyles(`<style>${TELEGRAM_PREVIEW_STYLE_TOKEN}</style>`, '.preview-shell { width: 920px; }');

  assert.match(html, /width: 920px/);
  assert.doesNotMatch(html, new RegExp(TELEGRAM_PREVIEW_STYLE_TOKEN));
});

test('buildEmbeddedPreviewFontCss inlines deterministic preview fonts', async () => {
  const css = await buildEmbeddedPreviewFontCss();

  assert.match(css, /TelegramPreviewSans/);
  assert.match(css, /TelegramPreviewMono/);
  assert.match(css, /data:font\/woff2;base64,/);
});

test('buildTelegramPreviewHtml includes the preview heading and sample survey data', async () => {
  const html = await buildTelegramPreviewHtml();

  assert.match(html, /Telegram message preview/);
  assert.match(html, /Customer onboarding/);
  assert.match(html, /Ada Lovelace/);
  assert.match(html, /https:\/\/example\.test\/pdf/);
  assert.match(html, /\.preview-shell/);
  assert.match(html, new RegExp(TELEGRAM_PREVIEW_SANS_FAMILY));
  assert.match(html, new RegExp(TELEGRAM_PREVIEW_MONO_FAMILY));
  assert.match(html, /data:font\/woff2;base64,/);
});
