-- Provenance for the one-line destination summary.
--
-- `destinations.summary` is the sentence that appears on every card and at the top of every
-- destination page, and for the ~65 destinations without a full risk report it is the ONLY
-- editorial text on the page. It frequently carries a hard, checkable claim — a tax rate, a
-- visitor count, a law that changed on a date — and until now it carried no indication of when
-- anyone last checked that claim or where it came from.
--
-- That is the same failure the risk sections were built to avoid, so the summary gets the same
-- two fields:
--   summary_reviewed_at  the date a person last verified the claims in `summary`
--   summary_sources      JSON [{title,url}], rendered under the summary
--
-- Deliberately SEPARATE from destinations.last_reviewed_at, which means "the risk report was
-- reviewed". Reusing that column would make a destination with a checked summary and no risk
-- report display "Last reviewed <date>" as though the whole report had been verified.
--
-- Both are NULL by default and stay NULL until someone actually does the work. A NULL renders
-- nothing at all, which is the honest state — an unchecked claim must never display a date.

ALTER TABLE destinations ADD COLUMN IF NOT EXISTS summary_reviewed_at TEXT;
ALTER TABLE destinations ADD COLUMN IF NOT EXISTS summary_sources TEXT;
