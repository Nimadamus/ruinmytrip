#!/usr/bin/env bash
# Start the local dev server on a known-good database, with no stale process able to answer.
#
# Why this exists
# ---------------
# On 2026-08-12 a server left running from an earlier batch was still bound to port 8099. A newly
# started server bound to the same port alongside it, and requests were answered by whichever won.
# The result was a full page sweep that reported six brand new pages as 404 while the older ones
# returned 200, because the answering process was reading a database from two batches earlier.
#
# That is the dangerous class of failure: not a crash, a plausible wrong answer. A check that
# silently tests the wrong build is worse than no check, because it gets reported as evidence.
#
# So this script refuses to leave anything to chance:
#   1. kills any process already listening on the port
#   2. provisions and health-checks the database via dev_db.php
#   3. starts exactly one server against that database
#   4. asserts the server is serving the same place count the database holds, and fails if not
#
# Usage: scripts/dev_server.sh [port]     (default 8099)
set -uo pipefail

PORT="${1:-8099}"
cd "$(dirname "$0")/.." || exit 1
LOGDIR="${TMPDIR:-/tmp}"
LOG="$LOGDIR/rmt_dev_server_$PORT.log"

# 1. free the port
PIDS=$(netstat -ano 2>/dev/null | grep ":$PORT .*LISTENING" | awk '{print $NF}' | sort -u)
if [ -n "$PIDS" ]; then
  echo "port $PORT held by: $PIDS -- terminating"
  for p in $PIDS; do taskkill //PID "$p" //F >/dev/null 2>&1 || kill -9 "$p" 2>/dev/null; done
  sleep 2
fi
if netstat -ano 2>/dev/null | grep -q ":$PORT .*LISTENING"; then
  echo "ERROR: port $PORT is still held after terminating. Refusing to start a second server." >&2
  exit 1
fi

# 2. known-good database
DB=$(php -c php.local.ini scripts/dev_db.php --quiet 2>/dev/null)
if [ -z "$DB" ]; then
  echo "ERROR: dev_db.php did not return a usable database." >&2
  php -c php.local.ini scripts/dev_db.php --check >&2
  exit 1
fi
echo "database: $DB"

# 3. exactly one server
nohup env RMT_SQLITE="$DB" php -c php.local.ini -S "127.0.0.1:$PORT" -t public public/router.php \
  > "$LOG" 2>&1 &
sleep 3

COUNT=$(netstat -ano 2>/dev/null | grep -c ":$PORT .*LISTENING")
if [ "$COUNT" != "1" ]; then
  echo "ERROR: expected exactly 1 listener on $PORT, found $COUNT." >&2
  exit 1
fi

# 4. prove the server is reading the database we just provisioned, not an older one
DB_PLACES=$(RMT_SQLITE="$DB" php -c php.local.ini -r 'require "app/bootstrap.php";
  echo (int) q_one("SELECT COUNT(*) n FROM places p JOIN place_editorial pe ON pe.place_id = p.id")["n"];')
SITEMAP_PLACES=$(curl -s "http://127.0.0.1:$PORT/sitemap.xml" | grep -cE "<loc>[^<]*/p/")

if [ "$DB_PLACES" != "$SITEMAP_PLACES" ]; then
  echo "ERROR: server is not serving this database." >&2
  echo "  database has $DB_PLACES editorial places, served sitemap lists $SITEMAP_PLACES." >&2
  echo "  A stale process is almost certainly answering. Do not trust any check run against it." >&2
  exit 1
fi

echo "ready:    http://127.0.0.1:$PORT  ($DB_PLACES editorial places, server matches database)"
echo "log:      $LOG"
