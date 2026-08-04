import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';
import { captureSettingsScreenshot, captureTelegramPreview, attachPageErrorLogger, findPluginConfigureUrl, login } from './browser-flows.mjs';
import { buildMaskedSettingsValues } from './sample-data.mjs';

export async function updateScreenshots(config, { browserType = chromium, logger = console } = {}) {
  await mkdir(config.outputDir, { recursive: true });

  const browser = await browserType.launch({ headless: config.headless });
  const context = await browser.newContext({
    viewport: { ...config.settingsViewport },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();

  attachPageErrorLogger(page, logger);

  try {
    await login(page, config);

    const configureUrl = await findPluginConfigureUrl(page, config);

    await captureSettingsScreenshot(page, {
      configureUrl,
      settingsScreenshotPath: config.settingsScreenshotPath,
      pluginName: config.pluginName,
      maskedSettingsValues: buildMaskedSettingsValues(),
      viewport: config.settingsViewport,
    });
    await captureTelegramPreview(page, {
      previewScreenshotPath: config.previewScreenshotPath,
      viewport: config.previewViewport,
    });

    logger.log(`Updated ${path.relative(config.repoRoot, config.settingsScreenshotPath)}`);
    logger.log(`Updated ${path.relative(config.repoRoot, config.previewScreenshotPath)}`);
  } finally {
    await browser.close();
  }
}
