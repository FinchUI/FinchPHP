# Workspace Smoke Log (Step27 Final Freeze)

Scope: Step27 clean-workspace install and smoke verification in current workspace (non-isolated).

## Environment

- Workspace: /Users/finch/Develop/FinchUX/finchphp
- PHP binary: /opt/homebrew/opt/php/bin/php
- Base URL: http://127.0.0.1:19931
- Database file: storage/data/finch_workspace_step27.sqlite

## Flow Checklist

1. Installer POST completed successfully (page contains 安装完成).
2. Admin login succeeded (redirect to /admin).
3. Published one post from admin.
4. Homepage rendered and contained the new post title.
5. Issued API token from admin tokens page.
6. API read succeeded: GET /api/v1/posts returned HTTP 200 with code=0.
7. Upload succeeded: POST /api/v1/uploads returned code=0.
8. Comment submit succeeded: POST /api/v1/posts/{postId}/comments returned HTTP 202 with code=0.
9. Default content theme marker check succeeded (data-theme-source=content-default).

## Captured Evidence

- Result marker: WS_SMOKE_STEP27_PASS
- Post id: 1
- Post title: Workspace Step27 ws-smoke-1781294555-20836
- Comment HTTP status: 202
- Token prefix: 2005802c

Key response headers:

- Cache-Control: no-cache, private
- Cache-Control: no-store, no-cache, must-revalidate
- X-API-Version: v1
- X-Content-Type-Options: nosniff

## Workspace State After Run

- config.php exists (temporarily during smoke run).
- storage/data/install.lock exists (temporarily during smoke run).
- storage/data/finch_workspace_step27.sqlite exists (temporarily during smoke run).

## Cleanup

- Post-run cleanup removed config.php, install lock, smoke sqlite DB, and temporary uploaded smoke file.
