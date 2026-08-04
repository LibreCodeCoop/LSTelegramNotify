import assert from 'node:assert/strict';
import test from 'node:test';
import { createScreenshotConfig, normalizeBaseUrl, parseHeadless, requireEnv } from '../../../../scripts/screenshots/config.mjs';

test('normalizeBaseUrl removes trailing slashes', () => {
  assert.equal(normalizeBaseUrl('http://localhost:8080///'), 'http://localhost:8080');
});

test('parseHeadless only disables headless mode for explicit false', () => {
  assert.equal(parseHeadless(undefined), true);
  assert.equal(parseHeadless('true'), true);
  assert.equal(parseHeadless('false'), false);
});

test('requireEnv throws when a required variable is blank', () => {
  assert.throws(
    () => requireEnv({ LIMESURVEY_ADMIN_USER: '   ' }, 'LIMESURVEY_ADMIN_USER'),
    /Missing required environment variable: LIMESURVEY_ADMIN_USER/
  );
});

test('createScreenshotConfig composes paths and reads environment values', () => {
  const config = createScreenshotConfig({
    LIMESURVEY_BASE_URL: 'http://localhost:8080/',
    LIMESURVEY_PLUGIN_NAME: 'MyPlugin',
    LIMESURVEY_ADMIN_USER: 'admin',
    LIMESURVEY_ADMIN_PASSWORD: 'secret',
    PLAYWRIGHT_HEADLESS: 'false',
  }, '/tmp/repo');

  assert.equal(config.baseUrl, 'http://localhost:8080');
  assert.equal(config.pluginName, 'MyPlugin');
  assert.equal(config.adminUser, 'admin');
  assert.equal(config.adminPassword, 'secret');
  assert.equal(config.headless, false);
  assert.equal(config.outputDir, '/tmp/repo/img');
  assert.equal(config.settingsScreenshotPath, '/tmp/repo/img/settings.png');
  assert.equal(config.previewScreenshotPath, '/tmp/repo/img/telegram_example.png');
  assert.deepEqual(config.settingsViewport, { width: 1280, height: 2200 });
  assert.deepEqual(config.previewViewport, { width: 1200, height: 1400 });
});
