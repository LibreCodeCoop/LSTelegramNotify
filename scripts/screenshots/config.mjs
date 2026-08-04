import path from 'node:path';

export const DEFAULT_BASE_URL = 'http://localhost:8080';
export const DEFAULT_PLUGIN_NAME = 'LSTelegramNotify';
export const SETTINGS_VIEWPORT = Object.freeze({ width: 1280, height: 2200 });
export const PREVIEW_VIEWPORT = Object.freeze({ width: 1200, height: 1400 });

export function createScreenshotConfig(env = process.env, repoRoot = process.cwd()) {
  return {
    repoRoot,
    outputDir: path.join(repoRoot, 'img'),
    settingsScreenshotPath: path.join(repoRoot, 'img', 'settings.png'),
    previewScreenshotPath: path.join(repoRoot, 'img', 'telegram_example.png'),
    baseUrl: normalizeBaseUrl(env.LIMESURVEY_BASE_URL || DEFAULT_BASE_URL),
    pluginName: env.LIMESURVEY_PLUGIN_NAME || DEFAULT_PLUGIN_NAME,
    adminUser: requireEnv(env, 'LIMESURVEY_ADMIN_USER'),
    adminPassword: requireEnv(env, 'LIMESURVEY_ADMIN_PASSWORD'),
    headless: parseHeadless(env.PLAYWRIGHT_HEADLESS),
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
