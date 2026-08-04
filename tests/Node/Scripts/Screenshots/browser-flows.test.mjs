import assert from 'node:assert/strict';
import test from 'node:test';
import { getPluginManagerPageUrl, shouldIgnorePageErrorMessage } from '../../../../scripts/screenshots/browser-flows.mjs';

test('getPluginManagerPageUrl uses the friendly route for the first plugin manager page', () => {
  assert.equal(
    getPluginManagerPageUrl('http://localhost:8080', 1),
    'http://localhost:8080/index.php/admin/pluginmanager/sa/index'
  );
  assert.equal(
    getPluginManagerPageUrl('http://localhost:8080', 3),
    'http://localhost:8080/index.php/admin/pluginmanager?sa=index&page=3'
  );
});

test('shouldIgnorePageErrorMessage filters the known CKEditor language error only', () => {
  assert.equal(shouldIgnorePageErrorMessage("Cannot read properties of null (reading 'langEntries')"), true);
  assert.equal(shouldIgnorePageErrorMessage('Something else exploded'), false);
});
