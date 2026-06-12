#!/usr/bin/env bash
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$REPO_ROOT/.git/autosync.pid"

cd "$REPO_ROOT" || exit 1

if [[ ! -f "$PID_FILE" ]]; then
  echo "AutoSync: not running"
  exit 0
fi

pid="$(cat "$PID_FILE" 2>/dev/null || true)"
if [[ -z "$pid" ]]; then
  rm -f "$PID_FILE"
  echo "AutoSync: not running"
  exit 0
fi

if kill -0 "$pid" >/dev/null 2>&1; then
  kill "$pid" >/dev/null 2>&1 || true
  if kill -0 "$pid" >/dev/null 2>&1; then
    kill -9 "$pid" >/dev/null 2>&1 || true
  fi
  echo "AutoSync: stopped (pid=$pid)"
else
  echo "AutoSync: stale pid file removed"
fi

rm -f "$PID_FILE"
