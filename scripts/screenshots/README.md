# Screenshot automation

This directory contains the maintainer-facing automation for validating and regenerating the documentation screenshots.

## Commands

- `npm run screenshots:check` renders fresh screenshots and compares their decoded pixels against the committed PNG files.
- `npm run screenshots:update` regenerates the committed screenshots.
- `SCREENSHOT_TARGETS=preview npm run screenshots:check` validates only `img/telegram_example.png`.
- `SCREENSHOT_TARGETS=preview npm run screenshots:update` refreshes only `img/telegram_example.png`.
- `LIMESURVEY_HOST_PORT=18080 docker compose -f scripts/screenshots/docker-compose.yml up -d --force-recreate` starts the same disposable LimeSurvey stack used by CI.
- `docker compose -f scripts/screenshots/docker-compose.yml down -v` removes that stack again.

## What is validated

- `img/settings.png` is captured from the LimeSurvey admin interface with masked values.
- `img/telegram_example.png` is rendered locally from sample data, so it does not need Telegram or a live LimeSurvey instance.

## Local setup

1. Install PHP dependencies with `composer install` so the mounted plugin contains `vendor/autoload.php`.
2. Install Node dependencies with `npm install`.
3. Install Chromium with `npm run playwright:install`.
4. If you want the same setup used in CI, start the disposable stack from `scripts/screenshots/docker-compose.yml` with `up -d --force-recreate` and export `LIMESURVEY_BASE_URL=http://127.0.0.1:18080`, `LIMESURVEY_ADMIN_USER=admin`, `LIMESURVEY_ADMIN_PASSWORD=admin`.
5. If you prefer using an existing LimeSurvey instance, copy `.env.example` to `.env` and adjust the credentials there.

Required for the settings screenshot:

- `LIMESURVEY_BASE_URL`
- `LIMESURVEY_ADMIN_USER`
- `LIMESURVEY_ADMIN_PASSWORD`

Useful optional overrides:

- `SCREENSHOT_TARGETS=preview|settings|settings,preview`
- `PLAYWRIGHT_HEADLESS=false`
- `SCREENSHOT_PIXELMATCH_THRESHOLD=0.1`
- `SCREENSHOT_MAX_DIFF_PIXELS=0`
- `SCREENSHOT_MAX_DIFF_PIXEL_RATIO=0`

## GitHub Actions behavior

- `Documentation Screenshots` starts a disposable LimeSurvey stack with Docker Compose (`up -d --force-recreate`) and validates both `img/settings.png` and `img/telegram_example.png` in a single job.
- The workflow should break when the plugin settings UI changes enough to make `img/settings.png` drift from the committed version.

When validation fails, the workflow summary tells you which command to run next and uploads diff artifacts under `test-results/screenshots/`.
