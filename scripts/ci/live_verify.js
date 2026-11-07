// Read-only verification of a deployed site on phone and desktop (2026-11-07).
// Opens pages, asserts what a visitor should see, never submits a form, never signs up.
// usage: node scripts/ci/live_verify.js https://ruinmytrip.com [shots_dir]
const { chromium } = require('playwright');
const [BASE, SHOTS] = process.argv.slice(2);
const results = [];
const check = (n, c, d = '') => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -- ' + String(d).slice(0, 240))); };
const SELFCHECK = 'utm_source=other&utm_medium=other&utm_campaign=selfcheck&utm_content=selfcheck'; // excluded from human counts (docs/qa)

const PAGES = [
  ['/', /Find out what could ruin your trip/, 'home hero'],
  ['/ruined', /From travelers/, 'ruined: traveler section'],
  ['/ruined?d=rome-italy', /official travel advice/i, 'ruined city: link to scams'],
  ['/d/rome-italy', /Scams and traps in Rome/, 'city scams section'],
  ['/d/bangkok-thailand', /Scams and traps in Bangkok/, 'bangkok scams section'],
  ['/blog/trip-went-wrong-what-you-can-claim', /1,519 Special Drawing Rights/, 'claims page'],
  ['/blog/barcelona-tourist-tax-2026', /at most 7 nights per person/, 'barcelona post corrected'],
  ['/tourist-tax-calculator?city=barcelona&tier=4&nights=10&people=2', /117\.60/, 'calculator barcelona cap'],
  ['/hosts-locals', /New stays and new host applications are paused/, 'hosting paused notice'],
  ['/hosts-locals/become-a-host', /New host applications are paused/, 'become-a-host paused'],
  ['/buddies', /Travel buddies|travel buddy/i, 'buddies'],
  ['/explore', /./, 'explore'],
  ['/reviews', /Traveler reviews/, 'reviews'],
  ['/map', /./, 'travel map'],
  ['/login', /Sign in|Log in/i, 'login'],
  ['/register', /./, 'register'],
  ['/terms', /Last updated September 2026/, 'terms date fixed'],
  // Phase 2 (2026-11-07)
  ['/d/lisbon-portugal', /Scams and traps in Lisbon/, 'lisbon scams section'],
  ['/d/mexico-city-mexico', /Happened to you\? Tell the next traveler/, 'scam item invites a warning'],
  ['/blog/westminster-abbey-tickets-2026', /32 pounds for an adult ticket/, 'westminster corrected'],
  ['/blog/edinburgh-castle-tickets-2026', /11\.50 pounds online/, 'edinburgh corrected'],
  ['/blog/venice-access-fee-2026', /the 2026 fee days are over/, 'venice update'],
  ['/affiliate', /Where a paid link can appear/, 'affiliate policy'],
];

(async () => {
  const b = await chromium.launch();
  for (const [dev, vp] of [['phone', { width: 390, height: 844, isMobile: true, hasTouch: true }], ['desktop', { width: 1366, height: 900 }]]) {
    const ctx = await b.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch });
    const p = await ctx.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    p.on('console', m => { if (m.type() === 'error' && !/Content Security Policy|Report-Only|favicon/.test(m.text())) errs.push(m.text()); });
    for (const [path, re, label] of PAGES) {
      const url = BASE + path + (path.includes('?') ? '&' : '?') + SELFCHECK;
      const r = await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(e => null);
      const st = r ? r.status() : 0;
      const html = r ? await p.content() : '';
      const text = r ? await p.locator('body').innerText().catch(() => '') : '';
      check(`${dev} ${label}: 200`, st === 200, st);
      check(`${dev} ${label}: content`, re.test(text), (text.match(/.{0,120}/) || [''])[0]);
      check(`${dev} ${label}: no PHP error text`, !/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/.test(html));
      const sw = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth).catch(() => 0);
      check(`${dev} ${label}: no horizontal scroll`, sw <= 1, sw);
      if (SHOTS && ['/', '/d/rome-italy', '/blog/trip-went-wrong-what-you-can-claim'].includes(path)) await p.screenshot({ path: `${SHOTS}/live_${dev}_${label.replace(/\W+/g, '_')}.png` });
    }
    check(`${dev} no script errors`, errs.length === 0, errs.slice(0, 3).join(' || '));
    await ctx.close();
  }
  // Phase 2: nav, no partner block while unconfigured, a review form that keeps the sentence.
  { const c = await b.newContext(); const p = await c.newPage();
    await p.goto(BASE + '/?' + SELFCHECK);
    check('nav: Warnings is a primary link', (await p.locator('nav[aria-label=Primary] a', { hasText: 'Warnings' }).count()) === 1);
    await p.goto(BASE + '/blog/trip-went-wrong-what-you-can-claim?' + SELFCHECK);
    check('no partner block until a program is switched on', (await p.locator('.partner-block').count()) === 0);
    await p.goto(BASE + '/review/new?src=home_warn&ruined=' + encodeURIComponent('The taxi rank quoted triple the meter price.') + '&' + SELFCHECK);
    check('the warning sentence opens in the main review box', (await p.locator('textarea[name=body]').inputValue()).includes('triple the meter'));
    await c.close(); }
  // Redirects, read with a plain request.
  const ctx = await b.newContext(); const rq = ctx.request;
  const slash = await rq.get(BASE + '/d/rome-italy/', { maxRedirects: 0 });
  check('slash twin is one 301 to the canonical path', slash.status() === 301 && /\/d\/rome-italy$/.test(slash.headers()['location'] || ''), slash.status() + ' ' + slash.headers()['location']);
  const evil = await rq.get(BASE + '//evil.com/', { maxRedirects: 0 });
  check('no off-site redirect', !/^(https?:)?\/\/evil/.test(evil.headers()['location'] || ''), evil.headers()['location']);
  await b.close();
  const f = results.filter(r => !r[1]);
  console.log(`\n${results.length - f.length}/${results.length} passed`);
  process.exit(f.length ? 1 : 0);
})();
