#!/usr/bin/env bash
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$REPO_ROOT/.git/autosync.pid"
LOG_FILE="$REPO_ROOT/.git/autosync.log"
DAEMON="$REPO_ROOT/scripts/auto-sync-daemon.sh"

cd "$REPO_ROOT" || exit 1

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "AutoSync: not a git repository: $REPO_ROOT" >&2
  exit 1
fi

if [[ -f "$PID_FILE" ]]; then
  pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [[ -n "$pid" ]] && kill -0 "$pid" >/dev/null 2>&1; then
    echo "AutoSync: already running (pid=$pid)"
    echo "AutoSync: log file: $LOG_FILE"
    exit 0
  fi
fi

nohup "$DAEMON" >>"$LOG_FILE" 2>&1 &
pid="$!"
echo "$pid" > "$PID_FILE"

echo "AutoSync: started (pid=$pid)"
echo "AutoSync: log file: $LOG_FILE"
