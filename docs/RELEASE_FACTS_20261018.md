# Guarded release: fact corrections of 2026-10-18 (branch fix/facts-20261018, NOT deployed)

What changes: text only, in database/editorial/blog.json (10 posts) and places.json (Zeughauskeller).
No title, slug, URL, canonical, robots or sitemap change. Five meta descriptions change because the
old ones stated a wrong fact: the summaries of san-francisco-2026, san-francisco-cable-car-2026,
phuket-elephant-sanctuary-tickets-2026 and miami-art-week-2026, and the Zeughauskeller description.
Every new figure was read on the official source on 2026-10-18 (list in scripts/editorial_fix_20261018.py).

Steps, only after Nima says go:
1. `python ~/tools/rmt_ops/prod_check.py snap before.json` (SEO invariants before).
2. `git checkout main && git pull --ff-only && git merge --no-ff fix/facts-20261018` then `git push origin main`
   (pre push gate, 141 suites).
3. `python ~/tools/rmt_ops/wait_deploy.py <sha>`; the container entrypoint upserts the editorial JSON.
4. `python ~/tools/rmt_ops/prod_check.py snap after.json` then `prod_check.py diff before.json after.json`: among its fixed pages only the Zeughauskeller description may change.
5. `python ~/tools/rmt_ops/prod_smoke.py <outdir>`: PROBLEMS must be none.
6. Spot check live: /blog/miami-art-week-2026 shows "InScope", /p/zeughauskeller-zurich shows "1487",
   /blog/san-francisco-2026 shows "24.4 million".
Rollback: `git revert -m 1 <merge sha> && git push origin main` (editorial upsert restores the old text).
