-- Ondine served its last dinner on George IV Bridge at Hogmanay 2024 (The Scotsman) and its own
-- site now lists only the St Andrews restaurant at Seaton House (checked 2026-10-29). The page
-- keeps its URL and its review and says the Edinburgh restaurant has closed.
UPDATE places SET status = 'permanently_closed' WHERE slug = 'ondine-edinburgh' AND status = 'active';
