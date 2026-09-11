#!/usr/bin/env bash
# Every build gate, and a non-zero exit the moment one of them is red.
#
# This exists because of a specific repeated mistake: running the suite inside a shell chain that
# ends in something harmless, like `echo fails=$fails && git push`. echo succeeds, so the chain
# continues, and a red gate is followed by a green-looking deploy. It happened twice.
#
# The fix is not to be more careful. The fix is that the only supported way to run the gates exits
# non-zero, and that the pre-push hook calls it, so a push cannot outrun a failure even when the
# person driving forgets. Fail closed.
#
#   scripts/gate.sh            every test
#   scripts/gate.sh -q         only the summary and the failures
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Find a PHP. The hook runs with whatever environment git gives it, which on a developer machine
# is often one where php is not on PATH, and a gate that cannot run is a gate that does not exist.
PHP=${PHP:-}
if [ -z "$PHP" ]; then
  for cand in php /c/Users/BL/tools/php83/php.exe "$ROOT/../tools/php83/php.exe"; do
    if command -v "$cand" >/dev/null 2>&1; then PHP="$cand"; break; fi
  done
fi
if [ -z "$PHP" ]; then
  echo "GATE CANNOT RUN: no php found. Set PHP=/path/to/php." >&2
  exit 1
fi
QUIET=${1:-}
fails=0
red=()

for t in "$ROOT"/tests/*_test.php; do
  out=$("$PHP" "$t" 2>&1)
  if [ $? -ne 0 ]; then
    fails=$((fails+1))
    red+=("$(basename "$t")")
    echo "RED  $(basename "$t")"
    echo "$out" | tail -6 | sed 's/^/     /'
  elif [ "$QUIET" != "-q" ]; then
    echo "ok   $(basename "$t")"
  fi
done

if [ "$fails" -ne 0 ]; then
  echo
  echo "GATE FAILED: $fails of $(ls "$ROOT"/tests/*_test.php | wc -l) -- ${red[*]}"
  exit 1
fi
echo "GATE PASSED: $(ls "$ROOT"/tests/*_test.php | wc -l) suites green"
