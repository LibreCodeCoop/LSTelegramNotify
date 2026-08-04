import assert from 'node:assert/strict';
import test from 'node:test';
import { PNG } from 'pngjs';
import { comparePngBuffers } from '../../../../scripts/screenshots/checker.mjs';

function createPngBuffer(width, height, pixels) {
  const image = new PNG({ width, height });

  for (let index = 0; index < pixels.length; index += 1) {
    const [red, green, blue, alpha] = pixels[index];
    const offset = index * 4;

    image.data[offset] = red;
    image.data[offset + 1] = green;
    image.data[offset + 2] = blue;
    image.data[offset + 3] = alpha;
  }

  return PNG.sync.write(image);
}

test('comparePngBuffers matches identical images', () => {
  const buffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [0, 0, 0, 255],
  ]);

  const result = comparePngBuffers(buffer, buffer);

  assert.equal(result.matches, true);
  assert.equal(result.diffPixels, 0);
  assert.equal(result.reason, null);
  assert.ok(result.diffBuffer);
});

test('comparePngBuffers reports changed pixels', () => {
  const expectedBuffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [0, 0, 0, 255],
  ]);
  const actualBuffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [255, 0, 0, 255],
  ]);

  const result = comparePngBuffers(expectedBuffer, actualBuffer, {
    pixelmatchThreshold: 0,
  });

  assert.equal(result.matches, false);
  assert.equal(result.diffPixels, 1);
  assert.match(result.reason, /Detected 1 different pixels/);
  assert.ok(result.diffBuffer);
});

test('comparePngBuffers can tolerate a small pixel delta', () => {
  const expectedBuffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [0, 0, 0, 255],
  ]);
  const actualBuffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [255, 0, 0, 255],
  ]);

  const result = comparePngBuffers(expectedBuffer, actualBuffer, {
    pixelmatchThreshold: 0,
    maxDiffPixels: 1,
  });

  assert.equal(result.matches, true);
  assert.equal(result.diffPixels, 1);
});

test('comparePngBuffers fails when image dimensions differ', () => {
  const expectedBuffer = createPngBuffer(1, 1, [
    [255, 255, 255, 255],
  ]);
  const actualBuffer = createPngBuffer(2, 1, [
    [255, 255, 255, 255],
    [255, 255, 255, 255],
  ]);

  const result = comparePngBuffers(expectedBuffer, actualBuffer);

  assert.equal(result.matches, false);
  assert.equal(result.diffPixels, Number.POSITIVE_INFINITY);
  assert.match(result.reason, /Image dimensions differ/);
  assert.equal(result.diffBuffer, null);
});
