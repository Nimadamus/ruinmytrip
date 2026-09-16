# The checks that are run against production

Three scripts, all Playwright, all run from a fresh browser per case because attribution is first
touch and a reused browser proves nothing.

* `campaign_ready.py` — every campaign window: the page answers, the campaign line names the right
  window, the prefilled trip link carries the city and both dates, the social preview is the purpose
  built card, canonical and robots are untouched, and a signed out click reaches signup with the
  whole link preserved and the city and dates named back.
* `cold_qa.py` — the five priority destination pages at 390px and 1280px: can a stranger arriving
  from social answer what this is, why they should care and what to do next, and are those controls
  within two screens.
* `attrib_qa.py` — seven arrival shapes, including a crawler, checking what the server records.

**Every one of them tags its traffic `utm_content=selfcheck`**, which the acquisition report reads
and subtracts from the human count. Without that tag these checks appear in the dashboard as
travelers, which is worse than not checking at all. If you write a new check, tag it.

Two traps already paid for:

* `curl/` is in the crawler list, correctly, so a curl based check with no user agent measures the
  crawler filter rather than the funnel. Send a browser user agent.
* Reusing one browser across campaigns tests first touch persistence, not the campaign line, and it
  will report six false failures while looking like a real finding.
