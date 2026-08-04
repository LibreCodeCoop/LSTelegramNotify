import { config as loadEnv } from 'dotenv';
import { createScreenshotConfig } from './screenshots/config.mjs';
import { updateScreenshots } from './screenshots/runner.mjs';

loadEnv();

const screenshotConfig = createScreenshotConfig();

await updateScreenshots(screenshotConfig);
