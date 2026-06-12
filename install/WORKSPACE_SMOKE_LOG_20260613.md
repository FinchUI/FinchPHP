# Workspace Smoke Log (2026-06-13)

Scope: Step 21 real-workspace install and manual smoke, executed in current workspace (non-isolated).

## Environment

- Workspace: /Users/finch/Develop/FinchUX/finchphp
- PHP binary: /opt/homebrew/opt/php/bin/php
- Base URL: [http://127.0.0.1:19981](http://127.0.0.1:19981)
- Database file: storage/data/finch_workspace_20260613.sqlite

## Flow Checklist

1. Installer POST completed successfully (page contains 安装完成).
2. Admin login succeeded (redirect to /admin).
3. Published one post from admin.
4. Homepage rendered and contained the new post title.
5. Issued API token from admin tokens page.
6. API read succeeded: GET /api/v1/posts returned HTTP 200 with code=0.
7. Upload succeeded: POST /api/v1/uploads returned HTTP 201 with code=0.
8. Comment submit succeeded: POST /api/v1/posts/{postId}/comments returned HTTP 202 with code=0.
9. Theme fallback check succeeded after forcing missing active_theme (homepage still HTTP 200 and contains published post).

## Captured Evidence

- Result marker: WS_SMOKE_PASS
- Post id: 1
- Post title: Workspace Smoke ws-smoke-1781292122-17672
- Comment HTTP status: 202
- Token prefix: 83c15591

Key response headers:

- Home cache header: Cache-Control: no-cache, private
- API cache header: Cache-Control: no-store, no-cache, must-revalidate
- API version header: X-API-Version: v1
- API hardening header: X-Content-Type-Options: nosniff

## Workspace State After Run

- config.php exists.
- storage/data/install.lock exists.
- storage/data/finch_workspace_20260613.sqlite exists.

## Notes

- This run intentionally kept the workspace installed for operator-side manual follow-up.
- Step 22 and Step 23 remain pending in PROJECT_COMPLETION_PHASES.md.
