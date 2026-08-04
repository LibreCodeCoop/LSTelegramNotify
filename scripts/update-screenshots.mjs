import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { config as loadEnv } from 'dotenv';
import { chromium } from 'playwright';

loadEnv();

const repoRoot = process.cwd();
const outputDir = path.join(repoRoot, 'img');
const settingsScreenshotPath = path.join(outputDir, 'settings.png');
const previewScreenshotPath = path.join(outputDir, 'telegram_example.png');
const baseUrl = normalizeBaseUrl(process.env.LIMESURVEY_BASE_URL || 'http://localhost:8080');
const pluginName = process.env.LIMESURVEY_PLUGIN_NAME || 'LSTelegramNotify';
const adminUser = requireEnv('LIMESURVEY_ADMIN_USER');
const adminPassword = requireEnv('LIMESURVEY_ADMIN_PASSWORD');
const headless = String(process.env.PLAYWRIGHT_HEADLESS || 'true').toLowerCase() !== 'false';

await mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless });
const context = await browser.newContext({
  viewport: { width: 1600, height: 2200 },
  deviceScaleFactor: 1,
});
const page = await context.newPage();

page.on('pageerror', (error) => {
  const message = String(error?.message || '');

  if (message.includes("Cannot read properties of null (reading 'langEntries')")) {
    return;
  }

  console.warn(`[pageerror] ${message}`);
});

try {
  await login(page);

  const configureUrl = await findPluginConfigureUrl(page, pluginName);

  await captureSettingsScreenshot(page, configureUrl);
  await captureTelegramPreview(page);

  console.log(`Updated ${path.relative(repoRoot, settingsScreenshotPath)}`);
  console.log(`Updated ${path.relative(repoRoot, previewScreenshotPath)}`);
} finally {
  await browser.close();
}

function requireEnv(name) {
  const value = process.env[name];

  if (!value || value.trim() === '') {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

function normalizeBaseUrl(url) {
  return url.replace(/\/+$/, '');
}

async function login(browserPage) {
  await browserPage.goto(`${baseUrl}/index.php/admin/authentication/sa/login`, {
    waitUntil: 'networkidle',
  });

  await browserPage.fill('#user', adminUser);
  await browserPage.fill('#password', adminPassword);

  await Promise.all([
    browserPage.waitForLoadState('networkidle'),
    browserPage.getByRole('button', { name: 'Log in' }).click(),
  ]);

  if (browserPage.url().includes('/authentication/sa/login')) {
    throw new Error('Login failed. Check LIMESURVEY_ADMIN_USER and LIMESURVEY_ADMIN_PASSWORD.');
  }
}

async function findPluginConfigureUrl(browserPage, plugin) {
  for (let pageNumber = 1; pageNumber <= 10; pageNumber += 1) {
    const listUrl = pageNumber === 1
      ? `${baseUrl}/index.php/admin/pluginmanager/sa/index`
      : `${baseUrl}/index.php/admin/pluginmanager?sa=index&page=${pageNumber}`;

    await browserPage.goto(listUrl, { waitUntil: 'networkidle' });

    const pluginRow = browserPage.locator('table tbody tr').filter({ hasText: plugin }).first();

    if (await pluginRow.count() === 0) {
      continue;
    }

    const configureLink = pluginRow.locator('a[href*="pluginmanager?sa=configure"]').first();

    if (await configureLink.count() === 0) {
      throw new Error(`Found ${plugin}, but it has no configure link. Check whether it is active and load-error free.`);
    }

    const href = await configureLink.getAttribute('href');

    if (!href) {
      throw new Error(`Could not read the configure link for ${plugin}.`);
    }

    return new URL(href, baseUrl).toString();
  }

  throw new Error(`Could not find ${plugin} in the LimeSurvey plugin manager.`);
}

async function captureSettingsScreenshot(browserPage, configureUrl) {
  await browserPage.goto(configureUrl, { waitUntil: 'networkidle' });
  await browserPage.getByRole('tab', { name: 'Settings' }).click();

  await browserPage.getByRole('textbox', { name: 'Auth Token' }).fill('123456789:bot-token-redacted');
  await browserPage.getByRole('textbox', { name: 'Chat id' }).fill('-1001234567890');
  const defaultTextField = browserPage.getByRole('textbox', { name: 'Default Text' });

  await defaultTextField.fill([
    'New Survey Completed!',
    'Title: <code>{{title}}</code>',
    'Respondent: <code>{{field:NAME.answer}}</code>',
  ].join('\n'));
  await defaultTextField.evaluate((element) => {
    element.scrollTop = 0;
    element.setSelectionRange(0, 0);
  });

  const settingsForm = browserPage.locator(`#pluginsettings-${pluginName}`);

  await defaultTextField.scrollIntoViewIfNeeded();
  await settingsForm.screenshot({ path: settingsScreenshotPath });
}

async function captureTelegramPreview(browserPage) {
  await browserPage.setViewportSize({ width: 1200, height: 1400 });
  await browserPage.setContent(buildTelegramPreviewHtml(), { waitUntil: 'load' });

  await browserPage.locator('.preview-shell').screenshot({ path: previewScreenshotPath });
}

function buildTelegramPreviewHtml() {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Telegram preview</title>
  <style>
    :root {
      color-scheme: light;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: linear-gradient(180deg, #d9efff 0%, #c7e4f7 100%);
      padding: 48px;
      color: #173042;
    }

    .preview-shell {
      width: 920px;
      border-radius: 32px;
      overflow: hidden;
      box-shadow: 0 28px 60px rgba(17, 66, 100, 0.18);
      background: #e5f3fb;
      border: 1px solid rgba(23, 48, 66, 0.08);
    }

    .preview-header {
      padding: 24px 28px;
      background: #4ea4d8;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .preview-header strong {
      display: block;
      font-size: 22px;
      font-weight: 700;
    }

    .preview-header span {
      display: block;
      margin-top: 4px;
      font-size: 14px;
      opacity: 0.92;
    }

    .preview-badge {
      border-radius: 999px;
      padding: 8px 14px;
      background: rgba(255, 255, 255, 0.18);
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
    }

    .preview-stage {
      padding: 40px 32px 52px;
      background:
        radial-gradient(circle at 25% 20%, rgba(255, 255, 255, 0.65), transparent 28%),
        radial-gradient(circle at 80% 18%, rgba(255, 255, 255, 0.45), transparent 24%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
    }

    .message-meta {
      text-align: center;
      font-size: 13px;
      color: rgba(23, 48, 66, 0.72);
      margin-bottom: 18px;
      font-weight: 600;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }

    .message-bubble {
      max-width: 640px;
      margin-left: auto;
      background: linear-gradient(180deg, #dcf8c6 0%, #d3f0be 100%);
      border-radius: 24px 24px 8px 24px;
      padding: 24px 24px 16px;
      box-shadow: 0 16px 32px rgba(71, 122, 68, 0.16);
      position: relative;
    }

    .message-bubble::after {
      content: '';
      position: absolute;
      right: -10px;
      bottom: 0;
      width: 0;
      height: 0;
      border-left: 14px solid #d3f0be;
      border-top: 12px solid transparent;
      border-bottom: 8px solid transparent;
    }

    .message-bubble h2 {
      margin: 0 0 16px;
      font-size: 24px;
      line-height: 1.2;
    }

    .message-bubble p {
      margin: 0 0 12px;
      font-size: 17px;
      line-height: 1.55;
    }

    .message-bubble code {
      font-family: "IBM Plex Mono", "SFMono-Regular", Consolas, monospace;
      font-size: 0.95em;
      border-radius: 8px;
      background: rgba(23, 48, 66, 0.08);
      padding: 2px 6px;
    }

    .message-bubble a {
      color: #0b68a2;
      text-decoration: none;
      font-weight: 600;
    }

    .message-divider {
      margin: 18px 0;
      border: 0;
      height: 1px;
      background: rgba(23, 48, 66, 0.12);
    }

    .message-footnote {
      display: flex;
      justify-content: flex-end;
      margin-top: 16px;
      font-size: 13px;
      color: rgba(23, 48, 66, 0.62);
      font-weight: 600;
    }
  </style>
</head>
<body>
  <section class="preview-shell">
    <header class="preview-header">
      <div>
        <strong>Telegram message preview</strong>
        <span>Local Playwright render with sample data — no external Telegram access required.</span>
      </div>
      <div class="preview-badge">LSTelegramNotify</div>
    </header>
    <div class="preview-stage">
      <div class="message-meta">Illustrative rendered output</div>
      <article class="message-bubble">
        <h2>New Survey Completed!</h2>
        <p>Title: <code>Customer onboarding</code></p>
        <p>SurveyId: <code>198146</code></p>
        <p>ResponseId: <code>66</code></p>
        <hr class="message-divider" />
        <p>Respondent: <code>Ada Lovelace</code></p>
        <p>Preferred language: <code>Português</code></p>
        <p>Score (raw): <code>10</code></p>
        <p>
          Links:
          <a href="https://example.test/pdf">PDF</a> ·
          <a href="https://example.test/details">Details</a> ·
          <a href="https://example.test/edit">Edit</a>
        </p>
        <div class="message-footnote">14:26</div>
      </article>
    </div>
  </section>
</body>
</html>`;
}
