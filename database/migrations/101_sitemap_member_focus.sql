-- The cached sitemap was built under the old rules and would keep serving place,
-- guide and blog URLs until it expired. Clearing it makes the next build the
-- member one. The pages themselves are unchanged.
DELETE FROM sitemap_cache;
