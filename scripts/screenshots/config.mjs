import path from 'node:path';

export const DEFAULT_BASE_URL = 'http://localhost:8080';
export const DEFAULT_PLUGIN_NAME = 'LSTelegramNotify';
export const SETTINGS_VIEWPORT = Object.freeze({ width: 1280, height: 2200 });
export const PREVIEW_VIEWPORT = Object.freeze({ width: 1200, height: 1400 });
export const DEFAULT_SCREENSHOT_TARGETS = Object.freeze(['settings', 'preview']);
export const VALID_SCREENSHOT_TARGETS = new Set(DEFAULT_SCREENSHOT_TARGETS);

export function createScreenshotConfig(env = process.env, repoRoot = process.cwd()) {
  const targets = parseScreenshotTargets(env.SCREENSHOT_TARGETS);

  return {
    repoRoot,
    outputDir: path.join(repoRoot, 'img'),
    settingsScreenshotPath: path.join(repoRoot, 'img', 'settings.png'),
    previewScreenshotPath: path.join(repoRoot, 'img', 'telegram_example.png'),
    screenshotDiffDir: path.join(repoRoot, 'test-results', 'screenshots'),
    targets,
    baseUrl: normalizeBaseUrl(env.LIMESURVEY_BASE_URL || DEFAULT_BASE_URL),
    pluginName: env.LIMESURVEY_PLUGIN_NAME || DEFAULT_PLUGIN_NAME,
    adminUser: targets.settings ? requireEnv(env, 'LIMESURVEY_ADMIN_USER') : null,
    adminPassword: targets.settings ? requireEnv(env, 'LIMESURVEY_ADMIN_PASSWORD') : null,
    headless: parseHeadless(env.PLAYWRIGHT_HEADLESS),
    pixelmatchThreshold: parseNonNegativeFloat(env.SCREENSHOT_PIXELMATCH_THRESHOLD, 0.1),
    maxDiffPixels: parseNonNegativeInteger(env.SCREENSHOT_MAX_DIFF_PIXELS, 0),
    maxDiffPixelRatio: parseNonNegativeFloat(env.SCREENSHOT_MAX_DIFF_PIXEL_RATIO, 0),
    settingsViewport: SETTINGS_VIEWPORT,
    previewViewport: PREVIEW_VIEWPORT,
  };
}

export function requireEnv(env, name) {
  const value = env[name];

  if (!value || value.trim() === '') {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

export function normalizeBaseUrl(url) {
  return url.replace(/\/+$/, '');
}

export function parseHeadless(value) {
  return String(value ?? 'true').toLowerCase() !== 'false';
}

export function parseScreenshotTargets(value) {
  const rawTargets = String(value ?? DEFAULT_SCREENSHOT_TARGETS.join(','))
    .split(',')
    .map((target) => target.trim().toLowerCase())
    .filter(Boolean);

  if (rawTargets.length === 0) {
    throw new Error('SCREENSHOT_TARGETS must include at least one target.');
  }

  const invalidTargets = rawTargets.filter((target) => !VALID_SCREENSHOT_TARGETS.has(target));

  if (invalidTargets.length > 0) {
    throw new Error(`Unsupported SCREENSHOT_TARGETS value(s): ${invalidTargets.join(', ')}`);
  }

  return Object.freeze({
    settings: rawTargets.includes('settings'),
    preview: rawTargets.includes('preview'),
  });
}

export function parseNonNegativeFloat(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === '') {
    return fallback;
  }

  const parsedValue = Number(String(value));

  if (!Number.isFinite(parsedValue) || parsedValue < 0) {
    throw new Error(`Expected a non-negative float, received: ${value}`);
  }

  return parsedValue;
}

export function parseNonNegativeInteger(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === '') {
    return fallback;
  }

  const parsedValue = Number(String(value));

  if (!Number.isInteger(parsedValue) || parsedValue < 0) {
    throw new Error(`Expected a non-negative integer, received: ${value}`);
  }

  return parsedValue;
}

export function getEnabledScreenshotTargets(targets) {
  return DEFAULT_SCREENSHOT_TARGETS.filter((target) => targets[target]);
}

export function buildScreenshotCommand(scriptName, targets) {
  const enabledTargets = getEnabledScreenshotTargets(targets);

  if (enabledTargets.length === DEFAULT_SCREENSHOT_TARGETS.length) {
    return `npm run ${scriptName}`;
  }

  return `SCREENSHOT_TARGETS=${enabledTargets.join(',')} npm run ${scriptName}`;
}
