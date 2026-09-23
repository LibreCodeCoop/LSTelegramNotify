import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import { buildScreenshotCommand } from './config.mjs';
import { captureScreenshots } from './runner.mjs';

export function comparePngBuffers(expectedBuffer, actualBuffer, {
  pixelmatchThreshold = 0.1,
  maxDiffPixels = 0,
  maxDiffPixelRatio = 0,
} = {}) {
  const expectedImage = PNG.sync.read(expectedBuffer);
  const actualImage = PNG.sync.read(actualBuffer);

  if (expectedImage.width !== actualImage.width || expectedImage.height !== actualImage.height) {
    return {
      matches: false,
      type: 'size',
      reason: `Image dimensions differ: expected ${expectedImage.width}x${expectedImage.height}, got ${actualImage.width}x${actualImage.height}`,
      diffPixels: Number.POSITIVE_INFINITY,
      diffPixelRatio: 1,
      expectedSize: { width: expectedImage.width, height: expectedImage.height },
      actualSize: { width: actualImage.width, height: actualImage.height },
      diffBuffer: null,
    };
  }

  const diffImage = new PNG({ width: expectedImage.width, height: expectedImage.height });
  const diffPixels = pixelmatch(
    expectedImage.data,
    actualImage.data,
    diffImage.data,
    expectedImage.width,
    expectedImage.height,
    { threshold: pixelmatchThreshold }
  );
  const totalPixels = expectedImage.width * expectedImage.height;
  const diffPixelRatio = totalPixels === 0 ? 0 : diffPixels / totalPixels;
  const matches = !(diffPixels > maxDiffPixels && diffPixelRatio > maxDiffPixelRatio);

  return {
    matches,
    type: 'pixels',
    reason: matches
      ? null
      : `Detected ${diffPixels} different pixels (${(diffPixelRatio * 100).toFixed(4)}% of the image).`,
    diffPixels,
    diffPixelRatio,
    expectedSize: { width: expectedImage.width, height: expectedImage.height },
    actualSize: { width: actualImage.width, height: actualImage.height },
    diffBuffer: PNG.sync.write(diffImage),
  };
}

export async function assertScreenshotsUpToDate(config, { logger = console, browserType } = {}) {
  const screenshots = await captureScreenshots(config, { logger, browserType });
  const refreshCommand = buildScreenshotCommand('screenshots:update', config.targets);

  await rm(config.screenshotDiffDir, { recursive: true, force: true });

  const failures = [];

  for (const screenshot of screenshots) {
    let expectedBuffer;

    try {
      expectedBuffer = await readFile(screenshot.outputPath);
    } catch (error) {
      const actualImage = PNG.sync.read(screenshot.buffer);

      failures.push({
        screenshot,
        reason: `Missing committed screenshot at ${screenshot.relativeOutputPath}.`,
        actualBuffer: screenshot.buffer,
        expectedBuffer: null,
        diffBuffer: null,
        comparison: {
          type: 'missing',
          diffPixels: null,
          diffPixelRatio: null,
          expectedSize: null,
          actualSize: { width: actualImage.width, height: actualImage.height },
        },
      });
      continue;
    }

    const result = comparePngBuffers(expectedBuffer, screenshot.buffer, {
      pixelmatchThreshold: config.pixelmatchThreshold,
      maxDiffPixels: config.maxDiffPixels,
      maxDiffPixelRatio: config.maxDiffPixelRatio,
    });

    if (result.matches) {
      logger.log(`Validated ${screenshot.relativeOutputPath}`);
      continue;
    }

    failures.push({
      screenshot,
      reason: result.reason,
      expectedBuffer,
      actualBuffer: screenshot.buffer,
      diffBuffer: result.diffBuffer,
      comparison: result,
    });
  }

  if (failures.length === 0) {
    return;
  }

  await mkdir(config.screenshotDiffDir, { recursive: true });

  const failureLines = [];

  for (const failure of failures) {
    const artifactPrefix = path.join(config.screenshotDiffDir, failure.screenshot.key);

    if (failure.expectedBuffer) {
      await writeFile(`${artifactPrefix}.expected.png`, failure.expectedBuffer);
    }

    await writeFile(`${artifactPrefix}.actual.png`, failure.actualBuffer);

    if (failure.diffBuffer) {
      await writeFile(`${artifactPrefix}.diff.png`, failure.diffBuffer);
    }

    await writeFile(
      `${artifactPrefix}.report.json`,
      JSON.stringify({
        key: failure.screenshot.key,
        path: failure.screenshot.relativeOutputPath,
        type: failure.comparison.type,
        reason: failure.reason,
        diffPixels: Number.isFinite(failure.comparison.diffPixels)
          ? failure.comparison.diffPixels
          : null,
        diffPixelRatio: failure.comparison.diffPixelRatio,
        expectedSize: failure.comparison.expectedSize,
        actualSize: failure.comparison.actualSize,
        limits: {
          pixelmatchThreshold: config.pixelmatchThreshold,
          maxDiffPixels: config.maxDiffPixels,
          maxDiffPixelRatio: config.maxDiffPixelRatio,
        },
      }, null, 2)
    );

    const artifactDir = path.relative(config.repoRoot, config.screenshotDiffDir);
    failureLines.push(`- ${failure.screenshot.relativeOutputPath}: ${failure.reason} Diff artifacts: ${artifactDir}/`);
  }

  throw new Error([
    'Documentation screenshots are out of date.',
    ...failureLines,
    `Fix: run \`${refreshCommand}\` and commit the updated PNG files.`,
  ].join('\n'));
}
