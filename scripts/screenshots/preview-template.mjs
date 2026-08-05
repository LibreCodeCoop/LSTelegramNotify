import { readFile } from 'node:fs/promises';

export const TELEGRAM_PREVIEW_STYLE_TOKEN = '__TELEGRAM_PREVIEW_STYLES__';
export const TELEGRAM_PREVIEW_HTML_URL = new URL('./assets/telegram-preview.html', import.meta.url);
export const TELEGRAM_PREVIEW_CSS_URL = new URL('./assets/telegram-preview.css', import.meta.url);
export const TELEGRAM_PREVIEW_SANS_FAMILY = 'TelegramPreviewSans';
export const TELEGRAM_PREVIEW_MONO_FAMILY = 'TelegramPreviewMono';

const PREVIEW_FONT_DEFINITIONS = Object.freeze([
  {
    family: TELEGRAM_PREVIEW_SANS_FAMILY,
    weight: 400,
    style: 'normal',
    url: new URL('../../node_modules/@fontsource/inter/files/inter-latin-400-normal.woff2', import.meta.url),
  },
  {
    family: TELEGRAM_PREVIEW_SANS_FAMILY,
    weight: 600,
    style: 'normal',
    url: new URL('../../node_modules/@fontsource/inter/files/inter-latin-600-normal.woff2', import.meta.url),
  },
  {
    family: TELEGRAM_PREVIEW_SANS_FAMILY,
    weight: 700,
    style: 'normal',
    url: new URL('../../node_modules/@fontsource/inter/files/inter-latin-700-normal.woff2', import.meta.url),
  },
  {
    family: TELEGRAM_PREVIEW_MONO_FAMILY,
    weight: 400,
    style: 'normal',
    url: new URL('../../node_modules/@fontsource/ibm-plex-mono/files/ibm-plex-mono-latin-400-normal.woff2', import.meta.url),
  },
]);

export async function readPreviewAsset(url) {
  return readFile(url, 'utf8');
}

export function buildFontDataUrl(fontBuffer, mimeType = 'font/woff2') {
  return `data:${mimeType};base64,${fontBuffer.toString('base64')}`;
}

export function buildFontFaceCss({ family, weight, style, source }) {
  return [
    '@font-face {',
    `  font-family: "${family}";`,
    `  src: url("${source}") format("woff2");`,
    `  font-style: ${style};`,
    `  font-weight: ${weight};`,
    '  font-display: block;',
    '}',
  ].join('\n');
}

export async function buildEmbeddedPreviewFontCss() {
  const fonts = await Promise.all(PREVIEW_FONT_DEFINITIONS.map(async (fontDefinition) => ({
    ...fontDefinition,
    source: buildFontDataUrl(await readFile(fontDefinition.url)),
  })));

  return fonts.map(buildFontFaceCss).join('\n\n');
}

export function inlinePreviewStyles(htmlTemplate, cssContent) {
  if (!htmlTemplate.includes(TELEGRAM_PREVIEW_STYLE_TOKEN)) {
    throw new Error(`Preview template is missing style token: ${TELEGRAM_PREVIEW_STYLE_TOKEN}`);
  }

  return htmlTemplate.replace(TELEGRAM_PREVIEW_STYLE_TOKEN, cssContent);
}

export async function buildTelegramPreviewHtml() {
  const [htmlTemplate, cssContent, embeddedFontCss] = await Promise.all([
    readPreviewAsset(TELEGRAM_PREVIEW_HTML_URL),
    readPreviewAsset(TELEGRAM_PREVIEW_CSS_URL),
    buildEmbeddedPreviewFontCss(),
  ]);

  return inlinePreviewStyles(htmlTemplate, `${embeddedFontCss}\n\n${cssContent}`);
}
