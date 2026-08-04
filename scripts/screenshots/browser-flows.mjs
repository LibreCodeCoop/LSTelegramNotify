import { buildTelegramPreviewHtml } from './preview-template.mjs';

export const IGNORED_PAGE_ERROR_MESSAGES = Object.freeze([
  "Cannot read properties of null (reading 'langEntries')",
]);

export function shouldIgnorePageErrorMessage(message) {
  return IGNORED_PAGE_ERROR_MESSAGES.some((ignoredMessage) => message.includes(ignoredMessage));
}

export function attachPageErrorLogger(page, logger = console) {
  page.on('pageerror', (error) => {
    const message = String(error?.message || '');

    if (shouldIgnorePageErrorMessage(message)) {
      return;
    }

    logger.warn(`[pageerror] ${message}`);
  });
}

export async function login(page, { baseUrl, adminUser, adminPassword }) {
  await page.goto(`${baseUrl}/index.php/admin/authentication/sa/login`, {
    waitUntil: 'networkidle',
  });

  await page.fill('#user', adminUser);
  await page.fill('#password', adminPassword);

  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByRole('button', { name: 'Log in' }).click(),
  ]);

  if (page.url().includes('/authentication/sa/login')) {
    throw new Error('Login failed. Check LIMESURVEY_ADMIN_USER and LIMESURVEY_ADMIN_PASSWORD.');
  }
}

export function getPluginManagerPageUrl(baseUrl, pageNumber) {
  if (pageNumber === 1) {
    return `${baseUrl}/index.php/admin/pluginmanager/sa/index`;
  }

  return `${baseUrl}/index.php/admin/pluginmanager?sa=index&page=${pageNumber}`;
}

export async function findPluginConfigureUrl(page, { baseUrl, pluginName, maxPages = 10 }) {
  for (let pageNumber = 1; pageNumber <= maxPages; pageNumber += 1) {
    await page.goto(getPluginManagerPageUrl(baseUrl, pageNumber), { waitUntil: 'networkidle' });

    const pluginRow = page.locator('table tbody tr').filter({ hasText: pluginName }).first();

    if (await pluginRow.count() === 0) {
      continue;
    }

    const configureLink = pluginRow.locator('a[href*="pluginmanager?sa=configure"]').first();

    if (await configureLink.count() === 0) {
      throw new Error(`Found ${pluginName}, but it has no configure link. Check whether it is active and load-error free.`);
    }

    const href = await configureLink.getAttribute('href');

    if (!href) {
      throw new Error(`Could not read the configure link for ${pluginName}.`);
    }

    return new URL(href, baseUrl).toString();
  }

  throw new Error(`Could not find ${pluginName} in the LimeSurvey plugin manager.`);
}

export async function captureSettingsScreenshot(page, {
  configureUrl,
  settingsScreenshotPath,
  pluginName,
  maskedSettingsValues,
  viewport,
}) {
  await page.setViewportSize({ ...viewport });
  await page.goto(configureUrl, { waitUntil: 'networkidle' });
  await page.getByRole('tab', { name: 'Settings' }).click();

  await page.getByRole('textbox', { name: 'Auth Token' }).fill(maskedSettingsValues.authToken);
  await page.getByRole('textbox', { name: 'Chat id' }).fill(maskedSettingsValues.chatId);

  const defaultTextField = page.getByRole('textbox', { name: 'Default Text' });

  await defaultTextField.fill(maskedSettingsValues.defaultText);
  await defaultTextField.evaluate((element) => {
    element.scrollTop = 0;
    element.setSelectionRange(0, 0);
  });

  const settingsForm = page.locator(`#pluginsettings-${pluginName}`);

  await defaultTextField.scrollIntoViewIfNeeded();
  await settingsForm.screenshot({ path: settingsScreenshotPath });
}

export async function captureTelegramPreview(page, { previewScreenshotPath, viewport }) {
  await page.setViewportSize({ ...viewport });
  await page.setContent(await buildTelegramPreviewHtml(), { waitUntil: 'load' });
  await page.locator('.preview-shell').screenshot({ path: previewScreenshotPath });
}
