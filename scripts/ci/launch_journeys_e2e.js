// Seven real-browser journeys (first visit, warnings search, guest review -> join -> confirm, profile,
// 18+ buddy post, returning member, search landings) on phone and desktop. Added 2026-11-07.
// usage: node scripts/ci/launch_journeys_e2e.js http://127.0.0.1:8098 /path/to/copy.sqlite|postgres://u:p@host:port/db [shots_dir]
// Point it at a COPY of database/preview.sqlite served with RMT_DESIGN_V2=all; it writes accounts.
// Real browser journeys against a local RuinMyTrip server (isolated DB copy).
// usage: node journeys.js BASE DB SHOTDIR
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const [BASE, DB, SHOTS] = process.argv.slice(2);
const ROOT = '/home/user/ruinmytrip';
const results = [];
const check = (n, c, d = '') => { results.push([n, !!c, d]); console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -- ' + String(d).slice(0, 300))); };
// DB is a SQLite path, or a postgres:// URL to run the same journeys against Postgres (production's driver).
const dbEnv = /^postgres(ql)?:\/\//.test(DB) ? { DATABASE_URL: DB, DB_SSLMODE: 'disable', APP_URL: BASE } : { RMT_SQLITE: DB };
const php = (code) => execFileSync('php', ['-r', 'require "app/bootstrap.php"; ' + code], { cwd: ROOT, env: { ...process.env, ...dbEnv, RMT_APP_URL: BASE } }).toString().trim();
// The place J3 reviews: the Lisbon aquarium when the database has it, else any public place.
const PLACE = JSON.parse(php(`$r=q_one("SELECT slug, name FROM places WHERE status='active' AND destination_id IS NOT NULL ORDER BY CASE WHEN slug='oceanario-de-lisboa-lisbon' THEN 0 ELSE 1 END, id LIMIT 1"); echo json_encode($r);`));
const PLACE_RE = new RegExp(PLACE.name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/\s+/)[0], 'i');
// The city J1/J5 search and post for: Lisbon when present, else any published city.
const CITY = JSON.parse(php(`$r=q_one("SELECT slug, name FROM destinations ORDER BY CASE WHEN slug='lisbon-portugal' THEN 0 ELSE 1 END, id LIMIT 1"); echo json_encode($r);`)).name;
const uid = (email) => php(`$r=q_one("SELECT id FROM users WHERE email=?",[${JSON.stringify(email)}]); echo $r["id"]??"";`);

async function pageHealth(p, label, mobile) {
  const sw = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  check(`${label}: no horizontal scroll`, sw <= 1, 'overflow ' + sw);
  const html = await p.content();
  check(`${label}: no PHP error text`, !/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/.test(html), (html.match(/(Fatal error|Warning:|Notice:|Deprecated:).{0,160}/) || [''])[0]);
  const h1 = await p.locator('h1').count();
  check(`${label}: has one h1`, h1 === 1, 'h1 count ' + h1);
  if (mobile) {
    const small = await p.evaluate(() => [...document.querySelectorAll('main a.btn, main button, main .btn')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.height < 40 && getComputedStyle(e).visibility !== 'hidden'; }).map(e => (e.innerText || e.getAttribute('aria-label') || '').trim().slice(0, 30) + ':' + Math.round(e.getBoundingClientRect().height)).slice(0, 6));
    check(`${label}: main buttons >= 40px`, small.length === 0, small.join(' | '));
  }
}

(async () => {
  const b = await chromium.launch();
  for (const [dev, vp] of [['phone', { width: 390, height: 844, isMobile: true, hasTouch: true }], ['desktop', { width: 1366, height: 900 }]]) {
    const ctx = await b.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch, deviceScaleFactor: 1 });
    // Only this site is under test: third-party requests (hot-linked photos, map tiles) are cut so a
    // slow or blocked CDN cannot hold a page's load event and read as an app failure.
    const host = new URL(BASE).host;
    await ctx.route('**/*', r => (new URL(r.request().url()).host === host ? r.continue() : r.abort()));
    const p = await ctx.newPage();
    const errors = [];
    p.on('pageerror', e => errors.push(e.message));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|Content Security Policy|Report-Only|net::ERR_FAILED/.test(m.text())) errors.push(m.text()); });
    const M = dev === 'phone';
    const shot = async (n) => SHOTS && p.screenshot({ path: `${SHOTS}/${dev}_${n}.png` });

    // J1 first-time visitor
    await p.goto(BASE + '/');
    await pageHealth(p, `${dev} J1 home`, M);
    // The hero's three first actions (2026-11-07): warn, explore, find people.
    const warn = p.locator('#act-warn-text');
    check(`${dev} J1 warn box visible on first screen`, await warn.isVisible() && (await warn.boundingBox()).y < vp.height * 1.25, JSON.stringify(await warn.boundingBox()));
    check(`${dev} J1 explore and companion actions present`, await p.locator('#act-explore').count() === 1 && await p.locator('#act-find-where').count() === 1);
    await warn.fill('The airport taxi rank quoted triple the meter price.');
    await p.locator('form:has(#act-warn-text) button[type=submit]').click(); await p.waitForLoadState();
    check(`${dev} J1 warning carries into the review form`, /\/review\/new/.test(p.url()) && (await p.locator('[name=what_ruined]').inputValue()).includes('triple the meter'), p.url());
    check(`${dev} J1 the sentence is in the main review box, ready to publish`, (await p.locator('textarea[name=body]').inputValue()).includes('triple the meter'));
    await p.goto(BASE + '/');
    await p.locator('#act-find-where').fill(CITY);
    await p.locator('form:has(#act-find-where) button[type=submit]').click(); await p.waitForLoadState();
    check(`${dev} J1 find companions lands on buddies for the city`, /\/buddies/.test(p.url()) && (await p.locator('main').first().innerText()).toLowerCase().includes(CITY.toLowerCase()), p.url());
    await shot('j1_review_form');

    // J2 traveler searching destination warnings
    await p.goto(BASE + '/');
    const search = p.locator('input[name=q]:visible').first();
    if (await search.count()) { await search.fill('Bangkok'); await search.press('Enter'); await p.waitForLoadState(); }
    else { await p.goto(BASE + '/search?q=Bangkok'); }
    check(`${dev} J2 search Bangkok finds the city`, /bangkok/i.test(await p.locator('main').innerText()), p.url());
    await pageHealth(p, `${dev} J2 search`, M);
    await p.goto(BASE + '/search?q=bangkok+scams');
    const scamTxt = await p.locator('main').innerText();
    check(`${dev} J2 'bangkok scams' search returns something useful`, /bangkok/i.test(scamTxt), scamTxt.slice(0, 200));
    await p.goto(BASE + '/d/bangkok-thailand');
    await pageHealth(p, `${dev} J2 city page`, M);
    const cityTxt = await p.locator('main').innerText();
    check(`${dev} J2 city page surfaces warnings/what went wrong`, /(scam|warning|went wrong|ruin)/i.test(cityTxt), 'no warning vocabulary on city page');
    await p.goto(BASE + '/ruined?d=bangkok-thailand');
    await pageHealth(p, `${dev} J2 ruined city`, M);
    await shot('j2_ruined');

    // J3 guest writes a review, then joins and confirms
    const email = `qa_${dev}_${Date.now()}@example.test`;
    await p.goto(BASE + '/p/' + PLACE.slug);
    await pageHealth(p, `${dev} J3 place page`, M);
    await p.locator('a[href*="src=place_rate"][href*="rating=2"]').first().click(); await p.waitForLoadState();
    check(`${dev} J3 star tap on place page prefills place + rating`, await p.locator('input[name=rating][value="2"]').isChecked(), p.url());
    await p.locator('textarea[name=body]').fill('The main tank is lovely but on a Saturday morning the school groups turned it into a queue the whole way round. Go on a weekday afternoon.');
    const wr = p.locator('[name=what_ruined]'); if (await wr.isVisible()) await wr.fill('Saturday morning crowds of school groups.');
    await p.locator('form:has(textarea[name=body]) button[type=submit], form:has(textarea[name=body]) button:not([type])').last().click();
    await p.waitForLoadState();
    check(`${dev} J3 guest review goes to join step`, /plan\/join|register|join/.test(p.url()), p.url());
    await shot('j3_after_submit');
    const em = p.locator('input[name=email]:visible').first();
    if (await em.count()) {
      await em.fill(email);
      await p.locator('input[name=password]:visible').first().fill('Correct-Horse-9-battery');
      const age = p.locator('input[name=age_ok]:visible'); if (await age.count()) await age.check();
      await p.locator('form:has(input[name=email]) button:visible').last().click();
      await p.waitForLoadState();
    }
    const id = uid(email);
    check(`${dev} J3 account created`, !!id, email);
    if (id) {
      const held = php(`echo q_one("SELECT status FROM reviews WHERE user_id=? ORDER BY id DESC",[${id}])["status"]??"none";`);
      check(`${dev} J3 review held until confirm (not public)`, held === 'draft', held);
      const tok = php(`echo rmt_token_issue(${id}, "verify");`);
      await p.goto(BASE + '/verify-email?token=' + tok);
      const btn = p.getByRole('button', { name: /confirm/i }).first();
      if (await btn.count()) { await btn.click(); await p.waitForLoadState(); }
      const after = php(`echo q_one("SELECT status FROM reviews WHERE user_id=? ORDER BY id DESC",[${id}])["status"]??"none";`);
      check(`${dev} J3 review published after confirm`, after === 'published', after);
      check(`${dev} J3 the just-published page offers to send it on`, /Send it to someone going/.test(await p.locator('main').first().innerText()), p.url());
      await shot('j3_after_confirm');

      // J4 profile
      const uname = php(`echo q_one("SELECT username FROM users WHERE id=?",[${id}])["username"];`);
      await p.goto(`${BASE}/u/${uname}/edit`);
      await pageHealth(p, `${dev} J4 profile edit`, M);
      const dn = p.locator('input[name=display_name]'); if (await dn.count()) await dn.fill('QA Traveler ' + dev);
      const bio = p.locator('textarea[name=bio]'); if (await bio.count()) await bio.fill('Slow traveler, museums and markets.');
      await p.locator('form:has([name=display_name]) button:visible').last().click(); await p.waitForLoadState();
      await p.goto(`${BASE}/u/${uname}`);
      await pageHealth(p, `${dev} J4 public profile`, M);
      const prof = await p.locator('main').innerText();
      check(`${dev} J4 profile shows name and review`, /QA Traveler/.test(prof) && PLACE_RE.test(prof.normalize('NFD').replace(/[\u0300-\u036f]/g, '')), prof.slice(0, 200));
      check(`${dev} J4 profile does not expose email`, !prof.includes(email));
      await shot('j4_profile');

      // J5 travel companion search + post
      await p.goto(BASE + '/buddies');
      await pageHealth(p, `${dev} J5 buddies`, M);
      await p.goto(BASE + '/buddies/new');
      if (/account\/age/.test(p.url())) {
        check(`${dev} J5 18+ gate asks for birthdate`, await p.locator('input[name=dob]').count() === 1);
        await p.locator('input[name=dob]').fill('1994-05-12');
        await p.locator('form:has(input[name=dob]) [type=submit]').click(); await p.waitForLoadState();
        check(`${dev} J5 gate returns to buddy form`, /buddies\/new/.test(p.url()), p.url());
      }
      await shot('j5_buddy_form');
      await p.locator('select[name=destination_id]').selectOption({ label: new RegExp(CITY) }).catch(async () => {
        const v = await p.locator('select[name=destination_id] option', { hasText: CITY }).first().getAttribute('value');
        await p.locator('select[name=destination_id]').selectOption(v);
      });
      const d = new Date(Date.now() + 20 * 864e5), e = new Date(Date.now() + 26 * 864e5);
      await p.locator('input[name=date_from]').fill(d.toISOString().slice(0, 10));
      await p.locator('input[name=date_to]').fill(e.toISOString().slice(0, 10));
      const t = p.locator('input[name=title]'); if (await t.count()) await t.fill('Solo in ' + CITY + ', looking for a museum and food buddy');
      const de = p.locator('textarea[name=description]'); if (await de.count()) await de.fill('First time in ' + CITY + '. Happy to share a dinner or a day trip with another solo traveler.');
      const tp = p.locator('select[name=travel_party]'); if (await tp.count()) { const v = await tp.locator('option').nth(1).getAttribute('value'); await tp.selectOption(v); }
      const sa = p.locator('input[name=safety_ack]'); if (await sa.count()) await sa.check({ force: true });
      await p.locator('form:has([name=destination_id]) [type=submit], form:has([name=destination_id]) button:not([type])').last().click(); await p.waitForLoadState();
      await shot('j5_after_buddy_post');
      const bp = php(`echo q_one("SELECT COUNT(*) c FROM buddy_posts WHERE user_id=?",[${id}])["c"];`);
      check(`${dev} J5 buddy post created`, +bp > 0, p.url() + ' ' + (await p.locator('.flash, .errors, .alert, [role=alert]').allInnerTexts()).join('|'));
      
      await pageHealth(p, `${dev} J5 after post`, M);
      await shot('j5_after_trip');

      // J6 returning user
      await ctx.clearCookies();
      await p.goto(BASE + '/login');
      await p.locator('input[name=email]').fill(email);
      await p.locator('input[name=password]').fill('Correct-Horse-9-battery');
      await p.locator('form:has(input[name=email]) button:visible').last().click(); await p.waitForLoadState();
      await p.goto(BASE + '/feed');
      check(`${dev} J6 returning user reaches feed`, /\/feed/.test(p.url()), p.url());
      await pageHealth(p, `${dev} J6 feed`, M);
      await shot('j6_feed');
      await ctx.clearCookies();
    }

    // J7 search visitor on SEO landing pages
    for (const path of ['/travel-buddies/thailand', '/tourist-tax-calculator', '/blog', '/guides', '/map']) {
      const r = await p.goto(BASE + path);
      check(`${dev} J7 ${path} 200`, r && r.status() === 200, r && r.status());
      if (r && r.status() === 200) await pageHealth(p, `${dev} J7 ${path}`, M);
    }
    check(`${dev} no JS errors across journeys`, errors.length === 0, errors.slice(0, 4).join(' || '));
    await ctx.close();
  }
  await b.close();
  const f = results.filter(r => !r[1]);
  console.log(`\n${results.length - f.length}/${results.length} passed`);
  process.exit(f.length ? 1 : 0);
})();
