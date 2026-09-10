import { buildTelegramPreviewHtml } from './preview-template.mjs';

export const IGNORED_PAGE_ERROR_MESSAGES = Object.freeze([
  "Cannot read properties of null (reading 'langEntries')",
]);

const LOGIN_PATH_FRAGMENT = '/authentication/sa/login';
const DEFAULT_LOGIN_MAX_ATTEMPTS = 3;
const LOGIN_RETRY_DELAY_MS = 1_000;

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

export async function login(page, { baseUrl, adminUser, adminPassword, loginMaxAttempts = DEFAULT_LOGIN_MAX_ATTEMPTS }) {
  const loginUrl = `${baseUrl}/index.php/admin/authentication/sa/login`;

  for (let attempt = 1; attempt <= loginMaxAttempts; attempt += 1) {
    await page.goto(loginUrl, {
      waitUntil: 'networkidle',
    });

    await page.fill('#user', adminUser);
    await page.fill('#password', adminPassword);

    await Promise.all([
      page.waitForURL((url) => !url.toString().includes(LOGIN_PATH_FRAGMENT), { timeout: 10_000 }).catch(() => null),
      page.getByRole('button', { name: 'Log in' }).click(),
    ]);

    await page.waitForLoadState('networkidle');

    if (!page.url().includes(LOGIN_PATH_FRAGMENT)) {
      return;
    }

    if (attempt < loginMaxAttempts) {
      await page.waitForTimeout(LOGIN_RETRY_DELAY_MS);
    }
  }

  throw new Error('Login failed. Check LIMESURVEY_ADMIN_USER and LIMESURVEY_ADMIN_PASSWORD.');
}

export function getPluginManagerScanFilesUrl(baseUrl) {
  return `${baseUrl}/index.php/admin/pluginmanager?sa=scanFiles`;
}

export function getPluginManagerPageUrl(baseUrl, pageNumber) {
  if (pageNumber === 1) {
    return `${baseUrl}/index.php/admin/pluginmanager/sa/index`;
  }

  return `${baseUrl}/index.php/admin/pluginmanager?sa=index&page=${pageNumber}`;
}

function escapeCssAttributeValue(value) {
  return String(value)
    .replaceAll('\\', '\\\\')
    .replaceAll('"', '\\"');
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function buildPluginInstallInputSelector(pluginName) {
  return `input[name="pluginName"][value="${escapeCssAttributeValue(pluginName)}"]`;
}

export function extractPluginInstallRequest(scanHtml, pluginName) {
  const escapedPluginName = escapeRegExp(pluginName);
  const formMatch = scanHtml.match(new RegExp(
    `<form[^>]+action=['"]([^'"]*pluginmanager\\?sa=installPluginFromFile)['"][\\s\\S]*?(?:<input[^>]+(?:name=['"]pluginName['"][^>]+value=['"]${escapedPluginName}['"]|value=['"]${escapedPluginName}['"][^>]+name=['"]pluginName['"])[^>]*>)[\\s\\S]*?<\\/form>`,
    'i'
  ));

  if (!formMatch) {
    return null;
  }

  const [formHtml, actionPath] = formMatch;
  const csrfMatch = formHtml.match(/<input[^>]+(?:name=['"]YII_CSRF_TOKEN['"][^>]+value=['"]([^'"]+)['"]|value=['"]([^'"]+)['"][^>]+name=['"]YII_CSRF_TOKEN['"])[^>]*>/i);
  const csrfToken = csrfMatch?.[1] || csrfMatch?.[2] || null;

  if (!csrfToken) {
    return null;
  }

  return {
    actionPath,
    form: {
      YII_CSRF_TOKEN: csrfToken,
      pluginName,
    },
  };
}

export function buildPluginActionSelector(action, pluginId) {
  return `a[data-post-url*="pluginmanager?sa=${action}"][data-post-datas='${JSON.stringify({ pluginId })}']`;
}

export function isPluginActionDisabled(className) {
  return String(className ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .includes('disabled');
}

async function findPlugin(page, { baseUrl, pluginName, maxPages = 10 }) {
  for (let pageNumber = 1; pageNumber <= maxPages; pageNumber += 1) {
    await page.goto(getPluginManagerPageUrl(baseUrl, pageNumber), { waitUntil: 'networkidle' });

    const pluginRow = page.locator('table tbody tr').filter({ hasText: pluginName }).first();

    if (await pluginRow.count() === 0) {
      continue;
    }

    const pluginId = await pluginRow.getAttribute('data-id');
    const configureLink = pluginRow.locator('a[href*="pluginmanager?sa=configure"]').first();
    const hasConfigureLink = await configureLink.count() > 0;
    const href = hasConfigureLink ? await configureLink.getAttribute('href') : null;

    return {
      row: pluginRow,
      pluginId: pluginId ? Number(pluginId) : null,
      configureUrl: href ? new URL(href, baseUrl).toString() : null,
    };
  }

  return null;
}

async function installPluginFromScanResults(page, { baseUrl, pluginName }) {
  const scanResponse = await page.context().request.get(getPluginManagerScanFilesUrl(baseUrl));
  const scanHtml = await scanResponse.text();
  const installRequest = extractPluginInstallRequest(scanHtml, pluginName);

  if (!installRequest) {
    return false;
  }

  await page.context().request.post(new URL(installRequest.actionPath, baseUrl).toString(), {
    form: installRequest.form,
  });

  return true;
}

async function openPluginActions(row) {
  const dropdownToggle = row.locator('button.ls-dropdown-toggle').first();

  await dropdownToggle.scrollIntoViewIfNeeded();
  await dropdownToggle.click({ force: true });
}

async function getPluginAction(page, action, pluginId) {
  return page.evaluate(({ expectedAction, expectedPluginId }) => {
    const expectedPostData = JSON.stringify({ pluginId: expectedPluginId });

    const actionLink = Array.from(document.querySelectorAll('a[data-post-url]')).find((link) => {
      const postUrl = link.getAttribute('data-post-url') || '';
      const postDatas = link.getAttribute('data-post-datas') || '';

      return postUrl.includes(`pluginmanager?sa=${expectedAction}`) && postDatas === expectedPostData;
    });

    if (!actionLink) {
      return null;
    }

    return {
      className: actionLink.className,
      text: actionLink.textContent || '',
    };
  }, {
    expectedAction: action,
    expectedPluginId: pluginId,
  });
}

async function triggerPluginAction(page, action, pluginId) {
  await page.evaluate(({ expectedAction, expectedPluginId }) => {
    const expectedPostData = JSON.stringify({ pluginId: expectedPluginId });

    const actionLink = Array.from(document.querySelectorAll('a[data-post-url]')).find((link) => {
      const postUrl = link.getAttribute('data-post-url') || '';
      const postDatas = link.getAttribute('data-post-datas') || '';

      return postUrl.includes(`pluginmanager?sa=${expectedAction}`) && postDatas === expectedPostData;
    });

    if (!actionLink) {
      throw new Error(`Missing plugin action link for action ${expectedAction} and plugin id ${expectedPluginId}.`);
    }

    actionLink.click();
  }, {
    expectedAction: action,
    expectedPluginId: pluginId,
  });

  const confirmButton = page.locator('#actionBtn');

  const confirmModalVisible = await confirmButton.waitFor({ state: 'visible', timeout: 1000 })
    .then(() => true)
    .catch(() => false);

  if (confirmModalVisible) {
    await confirmButton.click({ force: true });
    await page.locator('#confirmation-modal').waitFor({ state: 'hidden' });
  }

  await page.waitForLoadState('networkidle');
}

export async function ensurePluginConfigureUrl(page, { baseUrl, pluginName, maxPages = 10 }) {
  let plugin = await findPlugin(page, { baseUrl, pluginName, maxPages });

  if (!plugin) {
    await installPluginFromScanResults(page, { baseUrl, pluginName });
    plugin = await findPlugin(page, { baseUrl, pluginName, maxPages });
  }

  if (!plugin) {
    throw new Error(`Could not find ${pluginName} in the LimeSurvey plugin manager even after scanning plugin files.`);
  }

  if (!plugin.pluginId) {
    throw new Error(`Could not determine the plugin id for ${pluginName}.`);
  }

  await openPluginActions(plugin.row);

  const resetLoadErrorAction = await getPluginAction(page, 'resetLoadError', plugin.pluginId);

  if (resetLoadErrorAction) {
    await triggerPluginAction(page, 'resetLoadError', plugin.pluginId);
    plugin = await findPlugin(page, { baseUrl, pluginName, maxPages });

    if (!plugin) {
      throw new Error(`Reloaded ${pluginName}, but could not find it again in the plugin manager.`);
    }

    await openPluginActions(plugin.row);
  }

  const activateAction = await getPluginAction(page, 'activate', plugin.pluginId);
  const activateActionClassName = activateAction?.className;

  if (activateAction && !isPluginActionDisabled(activateActionClassName)) {
    await triggerPluginAction(page, 'activate', plugin.pluginId);
    plugin = await findPlugin(page, { baseUrl, pluginName, maxPages });

    if (!plugin) {
      throw new Error(`Activated ${pluginName}, but could not find it again in the plugin manager.`);
    }
  }

  if (!plugin.configureUrl) {
    throw new Error(`Found ${pluginName}, but it has no configure link. Check whether it is load-error free.`);
  }

  return plugin.configureUrl;
}

export async function findPluginConfigureUrl(page, { baseUrl, pluginName, maxPages = 10 }) {
  return ensurePluginConfigureUrl(page, { baseUrl, pluginName, maxPages });
}

async function waitForDocumentFonts(page) {
  await page.evaluate(async () => {
    if ('fonts' in document) {
      await document.fonts.ready;
    }
  });
}

export async function captureSettingsScreenshot(page, {
  configureUrl,
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
  await waitForDocumentFonts(page);
  return settingsForm.screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
  });
}

export async function captureTelegramPreview(page, { viewport }) {
  await page.setViewportSize({ ...viewport });
  await page.setContent(await buildTelegramPreviewHtml(), { waitUntil: 'load' });
  await waitForDocumentFonts(page);
  return page.locator('.preview-shell').screenshot({
    animations: 'disabled',
    caret: 'hide',
    scale: 'css',
  });
}
