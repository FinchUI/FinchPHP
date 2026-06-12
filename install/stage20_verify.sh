#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/homebrew/opt/php/bin/php}"
PORT="${PORT:-19880}"

if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi

fail() {
    echo "STAGE20_VERIFY_FAIL: $1"
    exit 1
}

for cmd in curl rsync grep sed mktemp; do
    command -v "$cmd" >/dev/null 2>&1 || fail "missing command: $cmd"
done

[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || fail "php executable not found"

WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/finchphp-stage20.XXXXXX")"
APP_DIR="$WORK_DIR/app"
COOKIE_FILE="$WORK_DIR/cookie.txt"
SERVER_LOG="$WORK_DIR/server.log"
SERVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" >/dev/null 2>&1 || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -rf "$WORK_DIR"
}

trap cleanup EXIT

extract_csrf() {
    local html="$1"
    printf '%s' "$html" | grep -o 'name="_token" value="[^"]*"' | head -n1 | sed 's/.*value="//;s/"$//'
}

BASE_URL="http://127.0.0.1:${PORT}"

rsync -a --exclude '.git' --exclude '.DS_Store' "$ROOT_DIR/" "$APP_DIR/"

# Ensure a clean install state inside isolated copy.
rm -f "$APP_DIR/config.php"
rm -f "$APP_DIR/storage/data/install.lock"
rm -f "$APP_DIR/storage/data/finch.sqlite"
rm -f "$APP_DIR/storage/data/finch_stage20.sqlite"

"$PHP_BIN" -S "127.0.0.1:${PORT}" -t "$APP_DIR" >"$SERVER_LOG" 2>&1 &
SERVER_PID="$!"

READY=0
for _ in $(seq 1 100); do
    if curl -fsS "$BASE_URL/install/" >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 0.1
done

[[ "$READY" == "1" ]] || fail "dev server did not become ready"

INSTALL_HTML="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST \
    --data-urlencode "site_name=Finch Stage20" \
    --data-urlencode "site_url=$BASE_URL" \
    --data-urlencode "timezone=UTC" \
    --data-urlencode "driver=sqlite" \
    --data-urlencode "table_prefix=fp_" \
    --data-urlencode "sqlite_database=storage/data/finch_stage20.sqlite" \
    --data-urlencode "mysql_host=localhost" \
    --data-urlencode "mysql_port=3306" \
    --data-urlencode "mysql_database=finchphp" \
    --data-urlencode "mysql_username=root" \
    --data-urlencode "mysql_password=" \
    --data-urlencode "admin_username=admin" \
    --data-urlencode "admin_email=admin@example.com" \
    --data-urlencode "admin_password=Admin123456" \
    --data-urlencode "admin_password_confirm=Admin123456" \
    "$BASE_URL/install/")"

printf '%s' "$INSTALL_HTML" | grep -Fq '安装完成' || fail "install did not complete"

curl -fsS -D "$WORK_DIR/home_headers.txt" -o "$WORK_DIR/home.html" "$BASE_URL/" >/dev/null
grep -iq '^X-Content-Type-Options: nosniff' "$WORK_DIR/home_headers.txt" || fail "missing X-Content-Type-Options on home"
grep -iq '^X-Frame-Options: SAMEORIGIN' "$WORK_DIR/home_headers.txt" || fail "missing X-Frame-Options on home"
grep -iq '^Content-Security-Policy:' "$WORK_DIR/home_headers.txt" || fail "missing Content-Security-Policy on home"
grep -Eiq '^Cache-Control: (no-cache, private|no-store, no-cache, must-revalidate)' "$WORK_DIR/home_headers.txt" || fail "missing cache policy on home"

curl -fsS -D "$WORK_DIR/admin_login_headers.txt" -o /dev/null "$BASE_URL/admin/login"
grep -iq '^Cache-Control: no-store, no-cache, must-revalidate' "$WORK_DIR/admin_login_headers.txt" || fail "missing no-store policy on admin/login"

LOGIN_HTML="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/admin/login")"
LOGIN_TOKEN="$(extract_csrf "$LOGIN_HTML")"
[[ -n "$LOGIN_TOKEN" ]] || fail "missing csrf token on admin/login"

LOGIN_HEADERS="$(curl -sS -D - -o /dev/null -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST \
    --data-urlencode "_token=$LOGIN_TOKEN" \
    --data-urlencode "username=admin" \
    --data-urlencode "password=Admin123456" \
    "$BASE_URL/admin/login")"

printf '%s' "$LOGIN_HEADERS" | tr -d '\r' | grep -Eq '^Location: /admin/?$' || fail "admin login did not redirect to /admin"

KEYWORD="stage20-cache-$RANDOM-$(date +%s)"
POST_TITLE="Stage20 Publish ${KEYWORD}"

SEARCH_BEFORE="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/search?q=$KEYWORD")"
if printf '%s' "$SEARCH_BEFORE" | grep -Fq "$POST_TITLE"; then
    fail "search precondition failed"
fi

POST_CREATE_HTML="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/admin/posts/create")"
POST_TOKEN="$(extract_csrf "$POST_CREATE_HTML")"
[[ -n "$POST_TOKEN" ]] || fail "missing csrf token on admin/posts/create"

POST_HEADERS="$(curl -sS -D - -o /dev/null -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST \
    --data-urlencode "_token=$POST_TOKEN" \
    --data-urlencode "title=$POST_TITLE" \
    --data-urlencode "slug=" \
    --data-urlencode "status=publish" \
    --data-urlencode "comment_status=open" \
    --data-urlencode "excerpt=Stage20 verify excerpt" \
    --data-urlencode "content=Stage20 verify content with ${KEYWORD}." \
    --data-urlencode "tags=stage20,verify" \
    "$BASE_URL/admin/posts/store")"

POST_ID="$(printf '%s' "$POST_HEADERS" | tr -d '\r' | sed -n 's/^Location: .*id=\([0-9][0-9]*\).*/\1/p' | tail -n1)"
[[ -n "$POST_ID" ]] || fail "unable to parse post id from post create redirect"

SEARCH_AFTER="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/search?q=$KEYWORD")"
printf '%s' "$SEARCH_AFTER" | grep -Fq "$POST_TITLE" || fail "search result missing published post (cache invalidation failed)"

TOKENS_HTML="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/admin/tokens")"
TOKENS_CSRF="$(extract_csrf "$TOKENS_HTML")"
[[ -n "$TOKENS_CSRF" ]] || fail "missing csrf token on admin/tokens"

curl -sS -D "$WORK_DIR/token_issue_headers.txt" -o /dev/null -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST \
    --data-urlencode "_token=$TOKENS_CSRF" \
    --data-urlencode "user_id=1" \
    --data-urlencode "name=stage20-verify" \
    --data-urlencode "abilities=*" \
    --data-urlencode "expires_days=0" \
    "$BASE_URL/admin/tokens/issue"

TOKEN_PAGE="$(curl -fsS -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/admin/tokens?issued=1")"
PLAIN_TOKEN="$(printf '%s' "$TOKEN_PAGE" | grep -Eo '[a-f0-9]{64}' | sed -n '2p')"
if [[ -z "$PLAIN_TOKEN" ]]; then
    PLAIN_TOKEN="$(printf '%s' "$TOKEN_PAGE" | grep -Eo '[a-f0-9]{64}' | sed -n '1p')"
fi
[[ -n "$PLAIN_TOKEN" ]] || fail "unable to extract plain api token"

POSTS_STATUS="$(curl -sS -D "$WORK_DIR/api_posts_headers.txt" -o "$WORK_DIR/api_posts.json" -w '%{http_code}' \
    -H "Authorization: Bearer $PLAIN_TOKEN" \
    "$BASE_URL/api/v1/posts")"

[[ "$POSTS_STATUS" == "200" ]] || fail "api posts endpoint returned status $POSTS_STATUS"
grep -Fq '"code":0' "$WORK_DIR/api_posts.json" || fail "api posts payload is not success"
grep -iq '^Cache-Control: no-store, no-cache, must-revalidate' "$WORK_DIR/api_posts_headers.txt" || fail "missing api cache-control header"
grep -iq '^X-API-Version: v1' "$WORK_DIR/api_posts_headers.txt" || fail "missing X-API-Version header"
grep -iq '^X-Content-Type-Options: nosniff' "$WORK_DIR/api_posts_headers.txt" || fail "missing X-Content-Type-Options on api"

printf 'stage20 upload check\n' >"$WORK_DIR/upload.txt"
UPLOAD_STATUS="$(curl -sS -o "$WORK_DIR/upload.json" -w '%{http_code}' \
    -H "Authorization: Bearer $PLAIN_TOKEN" \
    -F "file=@$WORK_DIR/upload.txt;type=text/plain" \
    "$BASE_URL/api/v1/uploads")"

[[ "$UPLOAD_STATUS" == "201" ]] || fail "upload api returned status $UPLOAD_STATUS"
grep -Fq '"code":0' "$WORK_DIR/upload.json" || fail "upload api payload is not success"

COMMENT_STATUS="$(curl -sS -o "$WORK_DIR/comment.json" -w '%{http_code}' \
    -H "Authorization: Bearer $PLAIN_TOKEN" \
    --data-urlencode "content=Stage20 api comment" \
    "$BASE_URL/api/v1/posts/$POST_ID/comments")"

if [[ "$COMMENT_STATUS" != "201" && "$COMMENT_STATUS" != "202" ]]; then
    fail "comment api returned status $COMMENT_STATUS"
fi
grep -Fq '"code":0' "$WORK_DIR/comment.json" || fail "comment api payload is not success"

DB_FILE="$APP_DIR/storage/data/finch_stage20.sqlite"
[[ -f "$DB_FILE" ]] || fail "sqlite database file not found"

"$PHP_BIN" -r '$db = new PDO("sqlite:" . $argv[1]); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $stmt = $db->prepare("UPDATE fp_system_setting SET value = ? WHERE name = ?"); $stmt->execute(["missing-theme-stage20", "active_theme"]);' "$DB_FILE"

THEME_STATUS="$(curl -sS -o "$WORK_DIR/theme_fallback.html" -w '%{http_code}' "$BASE_URL/")"
[[ "$THEME_STATUS" == "200" ]] || fail "theme fallback request returned status $THEME_STATUS"
grep -Fq "$POST_TITLE" "$WORK_DIR/theme_fallback.html" || fail "theme fallback page did not render expected content"

echo "STAGE20_HTTP_VERIFY_PASS post_id=$POST_ID token_prefix=${PLAIN_TOKEN:0:8}"
