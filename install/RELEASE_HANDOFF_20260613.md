# FinchPHP Release Handoff (2026-06-13)

This note captures the release-side validation baseline after Stage20/Step21/Step22 completion.

## 1. Prerequisites

- OS: macOS (current maintainer environment)
- PHP: /opt/homebrew/opt/php/bin/php (fallback to `php` in PATH)
- Required PHP extensions: pdo, pdo_sqlite or pdo_mysql, json, mbstring, session, openssl, fileinfo, gd, curl

## 2. Reproducible Validation Commands

Run in repository root:

```bash
PHP_BIN=/opt/homebrew/opt/php/bin/php
if [[ ! -x "$PHP_BIN" ]]; then PHP_BIN="$(command -v php)"; fi
while IFS= read -r file; do "$PHP_BIN" -l "$file" >/dev/null || exit 1; done < <(rg --files -g '*.php')
```

Expected output marker:

- PHP_LINT_PASS

Run Stage20 repeatable end-to-end verifier:

```bash
./install/stage20_verify.sh
```

Expected output marker:

- STAGE20_HTTP_VERIFY_PASS ...

## 3. Latest Recorded Results

- PHP lint: PASS (`PHP_LINT_PASS`)
- Stage20 verifier: PASS (`STAGE20_HTTP_VERIFY_PASS post_id=1 token_prefix=00534ff0`)
- Workspace smoke (non-isolated) log: [WORKSPACE_SMOKE_LOG_20260613.md](WORKSPACE_SMOKE_LOG_20260613.md)
- Workspace smoke (Step27 final freeze) log: [WORKSPACE_SMOKE_LOG_20260613_STEP27.md](WORKSPACE_SMOKE_LOG_20260613_STEP27.md)

## 4. Current Workspace Runtime State

Current workspace has been reset to uninstalled state after Step21 smoke:

- config.php does not exist
- storage/data/install.lock does not exist
- storage/data/finch_workspace_20260613.sqlite does not exist

## 5. Known Non-Blocking/Out-of-Scope Diagnostics

- `PROJECT_PLAN.md` markdown diagnostics are reconciled in Step22.
- `PROJECT_COMPLETION_PHASES.md` may still show markdown style warnings if strict markdown lint is run; these do not affect runtime behavior.
- Any diagnostics from sibling projects/workspaces are out of release scope for this repository.

Functional gap notice (post-audit):
- Step24-26 are now completed with isolated audit markers:
	- `STEP24_AUDIT_PASS`
	- `STEP25_AUDIT_PASS`
	- `STEP26_AUDIT_PASS`
- Step27 final freeze/sign-off is completed:
	- `WS_SMOKE_STEP27_PASS post_id=1 token_prefix=2005802c comment_status=202`

Release readiness conclusion:

- Program is ready for installation validation on a clean workspace.
- Workspace currently remains at uninstalled baseline after final smoke cleanup.

## 6. Rollback And Cleanup Reminders

If you need a fresh uninstalled workspace state:

```bash
rm -f config.php
rm -f storage/data/install.lock
rm -f storage/data/finch_workspace_20260613.sqlite
```

If preserving current installed state for manual QA, keep the files above and continue from `/admin/login`.
