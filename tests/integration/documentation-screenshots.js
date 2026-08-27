#!/usr/bin/env node
'use strict';

const path = require('path');

function argument(name) {
  const index = process.argv.indexOf(`--${name}`);
  return index === -1 ? undefined : process.argv[index + 1];
}

const playwrightModule = argument('playwright') || process.env.PLAYWRIGHT_MODULE;
const baseUrl = argument('base-url') || process.env.DOCS_SCREENSHOT_BASE_URL
  || 'http://127.0.0.1:18092';
const username = argument('username') || process.env.DOCS_SCREENSHOT_USERNAME || 'docs-admin';
const password = argument('password') || process.env.DOCS_SCREENSHOT_PASSWORD;
const browserExecutable = argument('browser') || process.env.DOCS_SCREENSHOT_BROWSER;
const outputDirectory = argument('output') || process.env.DOCS_SCREENSHOT_OUTPUT
  || path.resolve(__dirname, '../../docs/images');
const targetRoute = argument('route');
const probeAction = argument('probe-action');

if (!playwrightModule || !password || !browserExecutable) {
  throw new Error(
    '--playwright, --password, and --browser (or their environment equivalents) are required.'
  );
}

const fixtureHostname = new URL(baseUrl).hostname;
if (!['127.0.0.1', 'localhost', '::1'].includes(fixtureHostname)) {
  throw new Error('Documentation screenshots may run only against a loopback fixture.');
}

const { chromium } = require(playwrightModule);

const screenshotStyle = `
  *, *::before, *::after {
    animation-duration: 0s !important;
    transition-duration: 0s !important;
    caret-color: transparent !important;
  }
`;

async function stackScreenshots(sharp, screenshots, outputPath) {
  const gap = 24;
  const metadata = await Promise.all(screenshots.map((shot) => sharp(shot).metadata()));
  const width = Math.max(...metadata.map((item) => item.width));
  const height = metadata.reduce((sum, item) => sum + item.height, 0)
    + gap * (screenshots.length - 1);
  let top = 0;
  const composite = screenshots.map((shot, index) => {
    const entry = { input: shot, left: 0, top };
    top += metadata[index].height + gap;
    return entry;
  });

  await sharp({
    create: {
      width,
      height,
      channels: 4,
      background: { r: 241, g: 245, b: 249, alpha: 1 },
    },
  }).composite(composite).png({ compressionLevel: 9 }).toFile(outputPath);
}

async function main() {
  const browser = await chromium.launch({
    executablePath: browserExecutable,
    headless: true,
  });

  try {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 1050 },
      deviceScaleFactor: 1.5,
      colorScheme: 'light',
      locale: 'en-GB',
      timezoneId: 'UTC',
    });
    const page = await context.newPage();
    await page.goto(`${baseUrl}/admin`, { waitUntil: 'networkidle' });

    const usernameInput = page.locator('input[name="username"], input[type="text"]').first();
    const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
    await usernameInput.fill(username);
    await passwordInput.fill(password);
    await passwordInput.press('Enter');
    await page.waitForURL(
      (url) => !url.pathname.includes('/login'),
      { timeout: 20000 }
    );
    await page.waitForLoadState('networkidle');

    if (targetRoute) {
      await page.goto(`${baseUrl}${targetRoute}`, { waitUntil: 'networkidle' });
    }

    if (probeAction === 'filters') {
      await page.getByRole('button', { name: /^Filters/ }).click();
    } else if (probeAction === 'edit') {
      await page.getByRole('button', { name: 'Edit lead' }).first().click();
    } else if (probeAction === 'delete') {
      await page.getByRole('button', { name: 'Delete lead' }).first().click();
    }
    if (probeAction) {
      await page.waitForTimeout(1000);
    }

    if (process.argv.includes('--probe')) {
      console.log(`URL=${page.url()}`);
      console.log(`TITLE=${await page.title()}`);
      console.log((await page.locator('body').innerText()).slice(0, 8000));
      console.log('INTERACTIVE=' + JSON.stringify(await page.locator(
        'button, a, input, select, [role="button"], [role="combobox"]'
      ).evaluateAll((elements) => elements.slice(0, 120).map((element) => ({
        tag: element.tagName,
        text: (element.innerText || element.getAttribute('aria-label') || '').trim(),
        type: element.getAttribute('type'),
        name: element.getAttribute('name'),
        href: element.getAttribute('href'),
        role: element.getAttribute('role'),
        title: element.getAttribute('title'),
      })))));
      return;
    }

    const fs = require('fs');
    const sharp = require(path.resolve(playwrightModule, '../sharp'));
    fs.mkdirSync(outputDirectory, { recursive: true });

    const leadsRoute = `${baseUrl}/admin/plugin/goosialize-leads`;
    const pluginRoute = `${baseUrl}/admin/plugins/goosialize-leads`;
    const output = (filename) => path.join(outputDirectory, filename);
    const screenshot = () => page.screenshot({ style: screenshotStyle });
    const croppedScreenshot = (height) => page.screenshot({
      clip: { x: 0, y: 0, width: 1440, height },
      style: screenshotStyle,
    });
    const gotoLeads = async () => {
      await page.goto(leadsRoute, { waitUntil: 'networkidle' });
      await page.getByRole('button', { name: 'Export CSV' }).waitFor();
      const visibleText = await page.locator('body').innerText();
      const emails = visibleText.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+/gi) || [];
      if (!emails.length || emails.some((email) => !email.endsWith('@example.test'))
          || !visibleText.includes(' Demo')) {
        throw new Error('Screenshot fixture is not the required synthetic Demo dataset.');
      }
      await page.waitForTimeout(350);
    };

    await gotoLeads();
    await page.screenshot({
      path: output('admin2-leads-workspace.png'),
      style: screenshotStyle,
    });

    await page.getByRole('button', { name: /^Filters/ }).click();
    const filterSelects = page.locator('select');
    await filterSelects.nth(0).selectOption({ label: 'New' });
    await filterSelects.nth(1).selectOption({ label: 'Download' });
    await page.waitForTimeout(700);
    await page.screenshot({
      path: output('admin2-leads-filters.png'),
      style: screenshotStyle,
    });

    await gotoLeads();
    await page.getByRole('button', { name: 'Edit lead' }).first().click();
    await page.getByText('Edit Lead — Alex Demo', { exact: true }).waitFor();
    await page.screenshot({
      path: output('admin2-lead-edit.png'),
      style: screenshotStyle,
    });

    await gotoLeads();
    await page.getByRole('button', { name: 'Delete lead' }).first().click();
    await page.getByText('Delete Lead?', { exact: true }).waitFor();
    const deleteConfirmation = await croppedScreenshot(850);
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByText('Delete Lead?', { exact: true }).waitFor({ state: 'hidden' });
    await page.getByRole('button', { name: /^Filters/ }).click();
    await page.locator('select').nth(2).selectOption({ label: 'Deleted' });
    await page.getByRole('button', { name: 'Restore lead' }).waitFor();
    await page.waitForTimeout(500);
    const restoreWorkspace = await croppedScreenshot(850);
    await stackScreenshots(
      sharp,
      [deleteConfirmation, restoreWorkspace],
      output('admin2-delete-restore.png')
    );
    await page.getByRole('button', { name: 'Restore lead' }).click();
    await page.getByRole('button', { name: 'Restore', exact: true }).click();
    await page.waitForTimeout(500);

    await gotoLeads();
    const exportButton = page.getByRole('button', { name: 'Export CSV' });
    await exportButton.hover();
    await page.screenshot({
      path: output('admin2-csv-export.png'),
      style: screenshotStyle,
    });
    const downloadPromise = page.waitForEvent('download');
    await exportButton.click();
    const download = await downloadPromise;
    const csvFilename = download.suggestedFilename();
    if (!/^goosialize-leads-\d{4}-\d{2}-\d{2}-\d{4}\.csv$/.test(csvFilename)) {
      throw new Error(`Unexpected CSV filename: ${csvFilename}`);
    }
    await download.cancel();

    await page.goto(pluginRoute, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Forms', exact: true }).click();
    await page.getByText('Eligible Grav Forms', { exact: true }).waitFor();
    await page.waitForTimeout(350);
    const formsConfiguration = await croppedScreenshot(760);
    await page.getByRole('button', { name: 'Security', exact: true }).click();
    await page.getByText('Idempotency key version 1', { exact: true }).waitFor();
    await page.waitForTimeout(350);
    const securityConfiguration = await croppedScreenshot(760);
    await stackScreenshots(
      sharp,
      [formsConfiguration, securityConfiguration],
      output('plugin-configuration.png')
    );

    const expectedScreenshots = [
      'admin2-leads-workspace.png',
      'admin2-leads-filters.png',
      'admin2-lead-edit.png',
      'admin2-delete-restore.png',
      'admin2-csv-export.png',
      'plugin-configuration.png',
    ];
    for (const filename of expectedScreenshots) {
      const metadata = await sharp(output(filename)).metadata();
      if (metadata.format !== 'png' || metadata.width < 2000 || metadata.height < 1500) {
        throw new Error(`Screenshot quality check failed: ${filename}`);
      }
    }

    console.log(`OUTPUT_DIRECTORY=${outputDirectory}`);
    console.log(`CSV_FILENAME=${csvFilename}`);
    console.log('SCREENSHOTS_CREATED=6');
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
