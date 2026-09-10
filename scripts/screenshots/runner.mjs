import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';
import { captureSettingsScreenshot, captureTelegramPreview, attachPageErrorLogger, findPluginConfigureUrl, login } from './browser-flows.mjs';
import { buildMaskedSettingsValues } from './sample-data.mjs';

export async function captureScreenshots(config, { browserType = chromium, logger = console } = {}) {
  const browser = await browserType.launch({ headless: config.headless });
  const context = await browser.newContext({
    viewport: { ...config.settingsViewport },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();

  attachPageErrorLogger(page, logger);

  try {
    const screenshots = [];

    if (config.targets.settings) {
      await login(page, config);

      const configureUrl = await findPluginConfigureUrl(page, config);
      const settingsBuffer = await captureSettingsScreenshot(page, {
        configureUrl,
        pluginName: config.pluginName,
        maskedSettingsValues: buildMaskedSettingsValues(),
        viewport: config.settingsViewport,
      });

      screenshots.push({
        key: 'settings',
        outputPath: config.settingsScreenshotPath,
        relativeOutputPath: path.relative(config.repoRoot, config.settingsScreenshotPath),
        buffer: settingsBuffer,
      });
    }

    if (config.targets.preview) {
      const previewBuffer = await captureTelegramPreview(page, {
        viewport: config.previewViewport,
      });

      screenshots.push({
        key: 'preview',
        outputPath: config.previewScreenshotPath,
        relativeOutputPath: path.relative(config.repoRoot, config.previewScreenshotPath),
        buffer: previewBuffer,
      });
    }

    return screenshots;
  } finally {
    await browser.close();
  }
}

export async function updateScreenshots(config, { browserType = chromium, logger = console } = {}) {
  await mkdir(config.outputDir, { recursive: true });

  const screenshots = await captureScreenshots(config, { browserType, logger });

  for (const screenshot of screenshots) {
    await writeFile(screenshot.outputPath, screenshot.buffer);
    logger.log(`Updated ${screenshot.relativeOutputPath}`);
  }
}
