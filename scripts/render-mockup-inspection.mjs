import { chromium } from 'playwright';

const chunks = [];
for await (const chunk of process.stdin) {
  chunks.push(chunk);
}

const input = JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
const html = String(input.html || '');

const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'load', timeout: 10000 });
  await page.waitForTimeout(100);

  const visibleText = await page.evaluate(() => {
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== 'hidden'
        && style.display !== 'none'
        && Number(style.opacity) !== 0
        && rect.width > 0
        && rect.height > 0;
    };

    const normalize = (value) => value.replace(/\s+/g, ' ').trim();
    const values = [];
    const seen = new Set();

    const push = (value) => {
      const text = normalize(String(value || ''));
      if (text === '' || seen.has(text)) {
        return;
      }
      seen.add(text);
      values.push(text);
    };

    const walker = document.createTreeWalker(document.body || document.documentElement, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const node = walker.currentNode;
      const parent = node.parentElement;
      if (parent && isVisible(parent)) {
        push(node.textContent);
      }
    }

    for (const element of document.querySelectorAll('[aria-label], [alt], input[value], textarea')) {
      if (element instanceof HTMLElement && isVisible(element)) {
        push(element.getAttribute('aria-label'));
        push(element.getAttribute('alt'));
        push(element.value);
      }
    }

    return values;
  });

  const screenshot = await page.screenshot({ type: 'png', fullPage: true });
  const dimensions = await page.evaluate(() => ({
    width: Math.ceil(Math.max(document.documentElement.scrollWidth, document.body?.scrollWidth || 0, window.innerWidth)),
    height: Math.ceil(Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight || 0, window.innerHeight)),
  }));

  process.stdout.write(JSON.stringify({
    visible_text: visibleText,
    screenshot: {
      base64: screenshot.toString('base64'),
      width: dimensions.width,
      height: dimensions.height,
    },
  }));
} finally {
  await browser.close();
}
