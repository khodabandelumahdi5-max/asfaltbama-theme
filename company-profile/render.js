// Prints profile-en.html and profile-ar.html to PDF (A4). Run from the repo:
//   NODE_PATH=<dir with playwright> node company-profile/render.js
const { chromium } = require('playwright');
const path = require('path');
(async () => {
	const b = await chromium.launch();
	const p = await b.newPage();
	for (const lang of ['en', 'ar']) {
		await p.goto('file://' + path.join(__dirname, `profile-${lang}.html`), { waitUntil: 'load' });
		await p.evaluate(() => document.fonts.ready);
		await p.pdf({ path: path.join(__dirname, `Ofogh-Apadana-Pasargad-Company-Profile-${lang.toUpperCase()}.pdf`), format: 'A4', printBackground: true, preferCSSPageSize: true });
		await p.screenshot({ path: path.join(__dirname, `.preview-${lang}.png`), fullPage: true });
	}
	await b.close();
})();
