import { config as loadEnv } from 'dotenv';
import { buildScreenshotCommand, createScreenshotConfig } from './screenshots/config.mjs';
import { assertScreenshotsUpToDate } from './screenshots/checker.mjs';

loadEnv();

const screenshotConfig = createScreenshotConfig();

try {
  await assertScreenshotsUpToDate(screenshotConfig);
} catch (error) {
  const message = formatScreenshotCheckError(error);

  console.error(message);

  if (process.env.GITHUB_ACTIONS === 'true') {
    console.error(`::error title=Screenshot validation failed::${message.split('\n')[0]}`);
  }

  process.exit(1);
}

function formatScreenshotCheckError(error) {
  const originalMessage = error instanceof Error ? error.message : String(error);

  if (originalMessage.includes('Login failed.')) {
    return [
      'Screenshot validation could not log into LimeSurvey.',
      'Check LIMESURVEY_BASE_URL, LIMESURVEY_ADMIN_USER and LIMESURVEY_ADMIN_PASSWORD.',
      'If you want a disposable local LimeSurvey just for screenshots, run `docker compose up -d --force-recreate` from the repository root.',
      `If you only need the local preview image, run \`${buildScreenshotCommand('screenshots:check', { settings: false, preview: true })}\`.`,
    ].join('\n');
  }

  if (originalMessage.includes('Missing required environment variable:')) {
    return [
      originalMessage,
      `If you only need the local preview image, run \`${buildScreenshotCommand('screenshots:check', { settings: false, preview: true })}\`.`,
    ].join('\n');
  }

  return originalMessage;
}
