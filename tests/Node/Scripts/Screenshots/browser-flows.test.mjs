import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildPluginInstallInputSelector,
  buildPluginActionSelector,
  extractPluginInstallRequest,
  getPluginManagerPageUrl,
  getPluginManagerScanFilesUrl,
  isPluginActionDisabled,
  login,
  shouldIgnorePageErrorMessage,
} from '../../../../scripts/screenshots/browser-flows.mjs';

test('getPluginManagerScanFilesUrl points to the scan-files action', () => {
  assert.equal(
    getPluginManagerScanFilesUrl('http://localhost:8080'),
    'http://localhost:8080/index.php/admin/pluginmanager?sa=scanFiles'
  );
});

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

test('buildPluginInstallInputSelector targets the install form entry for the scanned plugin name', () => {
  assert.equal(
    buildPluginInstallInputSelector('LSTelegramNotify'),
    'input[name="pluginName"][value="LSTelegramNotify"]'
  );
});

test('extractPluginInstallRequest reads the install action and csrf token from scan HTML', () => {
  const scanHtml = `
    <div class="mb-3 col-12">
      <label class="form-label col-md-4">LSTelegramNotify</label>
      <form style="display: inline-block;" action="/index.php/admin/pluginmanager?sa=installPluginFromFile" method="post">
        <input type="hidden" value="csrf-double" name="YII_CSRF_TOKEN" />
        <input type='hidden' name='pluginName' value='LSTelegramNotify'/>
        <button class="btn btn-primary">Install</button>
      </form>
    </div>
  `;

  assert.deepEqual(extractPluginInstallRequest(scanHtml, 'LSTelegramNotify'), {
    actionPath: '/index.php/admin/pluginmanager?sa=installPluginFromFile',
    form: {
      YII_CSRF_TOKEN: 'csrf-double',
      pluginName: 'LSTelegramNotify',
    },
  });
});

test('extractPluginInstallRequest supports reversed attribute order and single quotes', () => {
  const scanHtml = `
    <form action='/index.php/admin/pluginmanager?sa=installPluginFromFile' method='post'>
      <input value='csrf-single' name='YII_CSRF_TOKEN' type='hidden'>
      <input value='LSTelegramNotify' name='pluginName' type='hidden'>
      <button>Install</button>
    </form>
  `;

  assert.deepEqual(extractPluginInstallRequest(scanHtml, 'LSTelegramNotify'), {
    actionPath: '/index.php/admin/pluginmanager?sa=installPluginFromFile',
    form: {
      YII_CSRF_TOKEN: 'csrf-single',
      pluginName: 'LSTelegramNotify',
    },
  });
});

test('buildPluginActionSelector targets the plugin action entry for the given plugin id', () => {
  assert.equal(
    buildPluginActionSelector('activate', 18),
    'a[data-post-url*="pluginmanager?sa=activate"][data-post-datas=\'{"pluginId":18}\']'
  );
});

test('isPluginActionDisabled detects the disabled dropdown class', () => {
  assert.equal(isPluginActionDisabled('dropdown-item disabled '), true);
  assert.equal(isPluginActionDisabled('dropdown-item'), false);
});

test('shouldIgnorePageErrorMessage filters the known CKEditor language error only', () => {
  assert.equal(shouldIgnorePageErrorMessage("Cannot read properties of null (reading 'langEntries')"), true);
  assert.equal(shouldIgnorePageErrorMessage('Something else exploded'), false);
});

test('login retries when the first fresh-stack attempt stays on the login page', async () => {
  let currentUrl = 'http://localhost:8080/index.php/admin/authentication/sa/login';
  let attempt = 0;
  const gotoCalls = [];
  const fills = [];
  const waitForTimeoutCalls = [];
  const clickCalls = [];

  const page = {
    async goto(url) {
      gotoCalls.push(url);
      currentUrl = url;
    },
    async fill(selector, value) {
      fills.push([selector, value]);
    },
    waitForURL() {
      return attempt === 0 ? Promise.reject(new Error('timeout')) : Promise.resolve();
    },
    getByRole(role, options) {
      return {
        click: async () => {
          clickCalls.push([role, options]);
          currentUrl = attempt === 0
            ? 'http://localhost:8080/index.php/admin/authentication/sa/login'
            : 'http://localhost:8080/index.php/dashboard/view';
          attempt += 1;
        },
      };
    },
    async waitForLoadState() {},
    url() {
      return currentUrl;
    },
    async waitForTimeout(value) {
      waitForTimeoutCalls.push(value);
    },
  };

  await login(page, {
    baseUrl: 'http://localhost:8080',
    adminUser: 'admin',
    adminPassword: 'admin',
  });

  assert.equal(gotoCalls.length, 2);
  assert.deepEqual(fills, [
    ['#user', 'admin'],
    ['#password', 'admin'],
    ['#user', 'admin'],
    ['#password', 'admin'],
  ]);
  assert.deepEqual(waitForTimeoutCalls, [1_000]);
  assert.deepEqual(clickCalls, [
    ['button', { name: 'Log in' }],
    ['button', { name: 'Log in' }],
  ]);
  assert.equal(currentUrl, 'http://localhost:8080/index.php/dashboard/view');
});

test('login fails after exhausting all retry attempts', async () => {
  let currentUrl = 'http://localhost:8080/index.php/admin/authentication/sa/login';
  let attempts = 0;

  const page = {
    async goto(url) {
      currentUrl = url;
    },
    async fill() {},
    waitForURL() {
      return Promise.reject(new Error('timeout'));
    },
    getByRole() {
      return {
        click: async () => {
          attempts += 1;
          currentUrl = 'http://localhost:8080/index.php/admin/authentication/sa/login';
        },
      };
    },
    async waitForLoadState() {},
    url() {
      return currentUrl;
    },
    async waitForTimeout() {},
  };

  await assert.rejects(
    () => login(page, {
      baseUrl: 'http://localhost:8080',
      adminUser: 'admin',
      adminPassword: 'admin',
    }),
    /Login failed\./
  );

  assert.equal(attempts, 3);
});
