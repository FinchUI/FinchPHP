#!/usr/bin/env bash
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 1

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "AutoSync: not a git repository: $REPO_ROOT" >&2
  exit 1
fi

INTERVAL="${AUTOSYNC_INTERVAL:-2}"
QUIET_SECONDS="${AUTOSYNC_QUIET_SECONDS:-5}"

has_pending_changes() {
  if ! git diff --quiet --ignore-submodules --; then
    return 0
  fi
  if ! git diff --cached --quiet --ignore-submodules --; then
    return 0
  fi
  if [[ -n "$(git ls-files --others --exclude-standard)" ]]; then
    return 0
  fi
  return 1
}

has_conflicts() {
  [[ -n "$(git diff --name-only --diff-filter=U)" ]]
}

commit_and_push() {
  local branch message

  branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
  if [[ -z "$branch" || "$branch" == "HEAD" ]]; then
    echo "AutoSync: skip, detached HEAD"
    return
  fi

  if has_conflicts; then
    echo "AutoSync: skip, merge conflict exists"
    return
  fi

  git add -A
  if git diff --cached --quiet --ignore-submodules --; then
    return
  fi

  message="chore(auto): sync $(date '+%Y-%m-%d %H:%M:%S')"
  if git commit -m "$message" >/dev/null 2>&1; then
    if git push origin "$branch" >/dev/null 2>&1; then
      echo "AutoSync: committed and pushed to origin/$branch at $(date '+%H:%M:%S')"
    else
      echo "AutoSync: push failed for origin/$branch, please run: git push origin $branch"
    fi
  else
    echo "AutoSync: commit failed, please check repository state"
  fi
}

echo "AutoSync: daemon started at $(date '+%Y-%m-%d %H:%M:%S')"
echo "AutoSync: interval=${INTERVAL}s quiet=${QUIET_SECONDS}s"

last_dirty_epoch=0

while true; do
  if has_pending_changes; then
    now_epoch="$(date +%s)"
    if [[ "$last_dirty_epoch" -eq 0 ]]; then
      last_dirty_epoch="$now_epoch"
    elif (( now_epoch - last_dirty_epoch >= QUIET_SECONDS )); then
      commit_and_push
      last_dirty_epoch=0
    fi
  else
    last_dirty_epoch=0
  fi

  sleep "$INTERVAL"
done
