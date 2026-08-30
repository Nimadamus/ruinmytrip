-- Migration 059 (sqlite) - see the pgsql file for why.
UPDATE places SET status = 'permanently_closed' WHERE status = 'closed';
