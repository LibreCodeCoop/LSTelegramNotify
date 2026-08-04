import { readFile } from 'node:fs/promises';

export const TELEGRAM_PREVIEW_STYLE_TOKEN = '__TELEGRAM_PREVIEW_STYLES__';
export const TELEGRAM_PREVIEW_HTML_URL = new URL('./assets/telegram-preview.html', import.meta.url);
export const TELEGRAM_PREVIEW_CSS_URL = new URL('./assets/telegram-preview.css', import.meta.url);

export async function readPreviewAsset(url) {
  return readFile(url, 'utf8');
}

export function inlinePreviewStyles(htmlTemplate, cssContent) {
  if (!htmlTemplate.includes(TELEGRAM_PREVIEW_STYLE_TOKEN)) {
    throw new Error(`Preview template is missing style token: ${TELEGRAM_PREVIEW_STYLE_TOKEN}`);
  }

  return htmlTemplate.replace(TELEGRAM_PREVIEW_STYLE_TOKEN, cssContent);
}

export async function buildTelegramPreviewHtml() {
  const [htmlTemplate, cssContent] = await Promise.all([
    readPreviewAsset(TELEGRAM_PREVIEW_HTML_URL),
    readPreviewAsset(TELEGRAM_PREVIEW_CSS_URL),
  ]);

  return inlinePreviewStyles(htmlTemplate, cssContent);
}
