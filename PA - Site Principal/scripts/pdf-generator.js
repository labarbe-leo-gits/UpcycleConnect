'use strict';

const puppeteer = require('puppeteer');

async function generate(url, outputPath) {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const page = await browser.newPage();

        await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 });

        await page.waitForFunction(() => {
            const el = document.getElementById('description-render');
            return !el || el.innerHTML.trim().length > 0;
        }, { timeout: 5000 }).catch(() => {});

        await page.pdf({
            path: outputPath,
            format: 'A4',
            printBackground: true,
            margin: { top: '2cm', bottom: '2cm', left: '2cm', right: '2cm' },
        });
    } finally {
        await browser.close();
    }
}

const [,, url, outputPath] = process.argv;

if (!url || !outputPath) {
    console.error('Usage: node pdf-generator.js <url> <output-file.pdf>');
    process.exit(1);
}

generate(url, outputPath)
    .then(() => {
        console.log('PDF saved to: ' + outputPath);
        process.exit(0);
    })
    .catch((err) => {
        console.error('PDF generation failed: ' + err.message);
        process.exit(1);
    });
