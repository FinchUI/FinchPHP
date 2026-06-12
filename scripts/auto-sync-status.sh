#!/usr/bin/env bash
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$REPO_ROOT/.git/autosync.pid"
LOG_FILE="$REPO_ROOT/.git/autosync.log"

cd "$REPO_ROOT" || exit 1

if [[ -f "$PID_FILE" ]]; then
  pid="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [[ -n "$pid" ]] && kill -0 "$pid" >/dev/null 2>&1; then
    echo "AutoSync: running (pid=$pid)"
  else
    echo "AutoSync: stopped (stale pid file)"
  fi
else
  echo "AutoSync: stopped"
fi

if [[ -f "$LOG_FILE" ]]; then
  echo "AutoSync: log tail"
  tail -n 20 "$LOG_FILE"
fi
