import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildScreenshotCommand,
  createScreenshotConfig,
  DEFAULT_PREVIEW_MAX_DIFF_PIXEL_RATIO,
  getDefaultAdminPassword,
  getDefaultAdminUser,
  getDefaultBaseUrl,
  getEnabledScreenshotTargets,
  getEnvOrFallback,
  getScreenshotComparisonOptions,
  normalizeBaseUrl,
  parseHeadless,
  parseNonNegativeFloat,
  parseNonNegativeInteger,
  parseScreenshotTargets,
  requireEnv,
} from '../../../../scripts/screenshots/config.mjs';

test('normalizeBaseUrl removes trailing slashes', () => {
  assert.equal(normalizeBaseUrl('http://localhost:8080///'), 'http://localhost:8080');
});

test('parseHeadless only disables headless mode for explicit false', () => {
  assert.equal(parseHeadless(undefined), true);
  assert.equal(parseHeadless('true'), true);
  assert.equal(parseHeadless('false'), false);
});

test('parseScreenshotTargets enables both targets by default and can limit to preview only', () => {
  assert.deepEqual(parseScreenshotTargets(undefined), { settings: true, preview: true });
  assert.deepEqual(parseScreenshotTargets('preview'), { settings: false, preview: true });
  assert.deepEqual(parseScreenshotTargets('settings, preview'), { settings: true, preview: true });
});

test('parseScreenshotTargets rejects unsupported values', () => {
  assert.throws(
    () => parseScreenshotTargets('preview,banana'),
    /Unsupported SCREENSHOT_TARGETS value\(s\): banana/
  );
});

test('getEnabledScreenshotTargets and buildScreenshotCommand keep target-specific commands readable', () => {
  assert.deepEqual(getEnabledScreenshotTargets({ settings: true, preview: false }), ['settings']);
  assert.deepEqual(getEnabledScreenshotTargets({ settings: true, preview: true }), ['settings', 'preview']);

  assert.equal(buildScreenshotCommand('screenshots:update', { settings: true, preview: true }), 'npm run screenshots:update');
  assert.equal(buildScreenshotCommand('screenshots:update', { settings: false, preview: true }), 'SCREENSHOT_TARGETS=preview npm run screenshots:update');
});

test('parseNonNegativeFloat and parseNonNegativeInteger validate numeric knobs', () => {
  assert.equal(parseNonNegativeFloat('0.25', 0.1), 0.25);
  assert.equal(parseNonNegativeInteger('12', 0), 12);

  assert.throws(() => parseNonNegativeFloat('-1', 0.1), /Expected a non-negative float/);
  assert.throws(() => parseNonNegativeInteger('1.2', 0), /Expected a non-negative integer/);
});

test('requireEnv throws when a required variable is blank', () => {
  assert.throws(
    () => requireEnv({ LIMESURVEY_ADMIN_USER: '   ' }, 'LIMESURVEY_ADMIN_USER'),
    /Missing required environment variable: LIMESURVEY_ADMIN_USER/
  );
});

test('getEnvOrFallback returns the explicit value when present and the fallback otherwise', () => {
  assert.equal(getEnvOrFallback({ FOO: 'bar' }, 'FOO', 'baz'), 'bar');
  assert.equal(getEnvOrFallback({ FOO: '   ' }, 'FOO', 'baz'), 'baz');
  assert.equal(getEnvOrFallback({}, 'FOO', 'baz'), 'baz');
});

test('getDefaultBaseUrl and default admin credentials follow the compose defaults and overrides', () => {
  assert.equal(getDefaultBaseUrl({}), 'http://127.0.0.1:18080');
  assert.equal(getDefaultAdminUser({}), 'admin');
  assert.equal(getDefaultAdminPassword({}), 'admin');

  assert.equal(getDefaultBaseUrl({
    LIMESURVEY_STACK_PUBLIC_SCHEME: 'https',
    LIMESURVEY_STACK_PUBLIC_HOST: 'example.test',
    LIMESURVEY_STACK_HOST_PORT: '19443',
  }), 'https://example.test:19443');
  assert.equal(getDefaultAdminUser({ LIMESURVEY_STACK_ADMIN_USER: 'alice' }), 'alice');
  assert.equal(getDefaultAdminPassword({ LIMESURVEY_STACK_ADMIN_PASSWORD: 'secret' }), 'secret');
});

test('createScreenshotConfig composes paths and reads environment values', () => {
  const config = createScreenshotConfig({
    LIMESURVEY_BASE_URL: 'http://localhost:8080/',
    LIMESURVEY_PLUGIN_NAME: 'MyPlugin',
    LIMESURVEY_ADMIN_USER: 'admin',
    LIMESURVEY_ADMIN_PASSWORD: 'secret',
    PLAYWRIGHT_HEADLESS: 'false',
    SCREENSHOT_PIXELMATCH_THRESHOLD: '0.2',
    SCREENSHOT_MAX_DIFF_PIXELS: '3',
    SCREENSHOT_MAX_DIFF_PIXEL_RATIO: '0.005',
  }, '/tmp/repo');

  assert.equal(config.baseUrl, 'http://localhost:8080');
  assert.equal(config.pluginName, 'MyPlugin');
  assert.equal(config.adminUser, 'admin');
  assert.equal(config.adminPassword, 'secret');
  assert.equal(config.headless, false);
  assert.equal(config.outputDir, '/tmp/repo/img');
  assert.equal(config.screenshotDiffDir, '/tmp/repo/test-results/screenshots');
  assert.equal(config.settingsScreenshotPath, '/tmp/repo/img/settings.png');
  assert.equal(config.previewScreenshotPath, '/tmp/repo/img/telegram_example.png');
  assert.deepEqual(config.targets, { settings: true, preview: true });
  assert.equal(config.pixelmatchThreshold, 0.2);
  assert.equal(config.maxDiffPixels, 3);
  assert.equal(config.maxDiffPixelRatio, 0.005);
  assert.deepEqual(config.comparisonOptions.settings, {
    pixelmatchThreshold: 0.2,
    maxDiffPixels: 3,
    maxDiffPixelRatio: 0.005,
  });
  assert.deepEqual(config.comparisonOptions.preview, {
    pixelmatchThreshold: 0.2,
    maxDiffPixels: 3,
    maxDiffPixelRatio: 0.005,
  });
  assert.deepEqual(config.settingsViewport, { width: 1280, height: 2200 });
  assert.deepEqual(config.previewViewport, { width: 1200, height: 1400 });
});

test('createScreenshotConfig uses the local compose defaults for settings validation when no explicit env is provided', () => {
  const config = createScreenshotConfig({}, '/tmp/repo');

  assert.equal(config.baseUrl, 'http://127.0.0.1:18080');
  assert.equal(config.pluginName, 'LSTelegramNotify');
  assert.equal(config.adminUser, 'admin');
  assert.equal(config.adminPassword, 'admin');
  assert.equal(config.headless, true);
  assert.equal(config.comparisonOptions.settings.maxDiffPixelRatio, 0);
  assert.equal(config.comparisonOptions.preview.maxDiffPixelRatio, DEFAULT_PREVIEW_MAX_DIFF_PIXEL_RATIO);
});

test('createScreenshotConfig does not require admin credentials for preview-only validation', () => {
  const config = createScreenshotConfig({
    SCREENSHOT_TARGETS: 'preview',
  }, '/tmp/repo');

  assert.deepEqual(config.targets, { settings: false, preview: true });
  assert.equal(config.adminUser, null);
  assert.equal(config.adminPassword, null);
});

test('getScreenshotComparisonOptions lets preview override its own drift budget', () => {
  const comparisonOptions = getScreenshotComparisonOptions({
    SCREENSHOT_PIXELMATCH_THRESHOLD: '0.1',
    PREVIEW_SCREENSHOT_MAX_DIFF_PIXEL_RATIO: '0.0025',
  });

  assert.deepEqual(comparisonOptions.settings, {
    pixelmatchThreshold: 0.1,
    maxDiffPixels: 0,
    maxDiffPixelRatio: 0,
  });
  assert.deepEqual(comparisonOptions.preview, {
    pixelmatchThreshold: 0.1,
    maxDiffPixels: 0,
    maxDiffPixelRatio: 0.0025,
  });
});
