# Finch PHP Remaining Implementation Phases

> Status source: actual workspace after install wizard/API stages, aligned with PROJECT_PLAN directory plan.

## Stage 13 - Directory Baseline And Install Safety

Goal: make the planned runtime/content/static directories exist and safe before larger feature work.

Scope:
- Create `content/` baseline directories: plugins, modules, themes, uploads.
- Create `system/script/`, `system/assets/css/`, `system/assets/img/`, `system/Theme/`, `system/Helper/`, `system/Languages/` baseline files.
- Create `storage/cache/`, `storage/cache/thumb/`, `storage/logs/`, `storage/data/` guard files.
- Add upload execution-blocking examples for Apache/Nginx.
- Add install lock handling so a completed install leaves an explicit lock in storage.

Acceptance:
- `GET /install/` still renders before install.
- HTTP POST SQLite install creates config, DB tables, admin, and install lock.
- Cleanup returns workspace to uninstalled state.
- `php -l` passes.

## Stage 14 - Frontend Template Runtime MVP

Goal: let an installed site render real frontend pages through the planned theme/template layer.

Scope:
- Implement `Core/Template.php`, `Core/Asset.php`, `Core/TemplateContext.php`.
- Add `Helper/TemplateTags.php`, `Helper/Security.php`, `Helper/Utility.php`.
- Add default `system/Theme` templates: header, footer, index, single, page, sidebar.
- Add controllers: PostController, PageController, CategoryController, TagController, SearchController.
- Register frontend rewrite/active routes for home, post detail, page detail, category/tag/search lists.

Acceptance:
- Installed site homepage renders published posts.
- Single post/page/category/tag routes render through default theme.
- Template tags work from context without globals.

## Stage 15 - Content Write And Admin MVP

Goal: make the CMS usable from the backend for articles, pages, categories, tags, and comments.

Scope:
- Extend `PostService` with create/update/publish/trash/restore transactions.
- Add slug generation, excerpt generation, primary category sync, tag/category count maintenance.
- Add `HtmlCleaner` first pass for post/comment profiles.
- Add admin controllers: PostAdmin, PageAdmin, CategoryAdmin, TagAdmin, CommentAdmin.
- Add minimal admin layout/assets and forms.

Acceptance:
- After install, admin can log in, create/edit/publish an article and page.
- Category/tag relations and counts remain correct.
- Public frontend/API sees newly published content.

## Stage 16 - Upload And Media Safety

Goal: implement the upload pipeline before rich editor/image workflows depend on it.

Scope:
- Implement `UploadService`, `Thumbnail`, upload validation, MIME/extension checks, randomized names.
- Add image dimension detection and GD rebuild for supported image formats.
- Add UploadAdmin and UploadApi.
- Integrate Quill image upload endpoint later through this service.

Acceptance:
- Admin/API upload accepts allowed files and rejects dangerous extensions/MIME mismatches.
- Upload records are written to DB and files land under `content/uploads/YYYY/MM/`.
- PHP execution is blocked in upload directories by generated server hints.

## Stage 17 - User, Tokens, And System Admin Completion

Goal: complete core operational backend surfaces.

Scope:
- Add UserAdmin and role assignment basics.
- Add API token management UI.
- Expand SettingAdmin into grouped settings: basic, reading, comments, permalinks, API, mail.
- Add UserApi where appropriate.

Acceptance:
- Admin can manage users, issue/revoke API tokens, and update grouped settings.
- Existing API auth/abilities continue to pass.

## Stage 18 - Plugin, Theme, Module Foundations

Goal: add extension loading without implementing every provider in full.

Scope:
- Implement PluginService, ThemeService, ModuleService skeletons with metadata scanning.
- Add settings models where missing: ThemeSetting, PluginSetting, ModuleSetting.
- Add theme discovery/switching basics.
- Add module enable/disable basics.
- Add placeholder preinstalled plugin metadata for tabler/quill/provider plugins.

Acceptance:
- Installed site can discover default theme and preinstalled plugin metadata.
- Admin can list extension metadata without fatal errors.

## Stage 19 - Provider Facades And Optional Features

Goal: introduce facade seams for optional services while keeping graceful degradation.

Scope:
- Add MailService, SmsService, CaptchaService, SearchService, AiService, SocialLoginService facades.
- Add missing models: AiModel, AiLog, SmsCode, UserSocialAccount, Module, Migration model wrapper if needed.
- Add minimal SearchService LIKE fallback.

Acceptance:
- Facades return deterministic fallback states when providers are absent.
- System does not fatal when optional plugins are missing.

## Stage 20 - Cache, Hardening, Docs, And Release Checks

Goal: polish runtime safety and release readiness.

Scope:
- Implement Cache manager and cache invalidation groups.
- Add API/cache/security headers where appropriate.
- Add install/deploy docs and API docs.
- Add repeatable verification scripts or test harness.
- Reconcile PROJECT_PLAN markdown lint separately.

Acceptance:
- Fresh install, login, publish content, upload, API read, comments, theme fallback all pass from a clean workspace.
- No PHP lint or runtime diagnostics in implemented code.

## Remaining Work Snapshot (2026-06-13)

Current status:
- Stage 20 code scope is implemented and isolated HTTP verification script is passing.
- Full-project PHP lint is passing for implemented runtime files.

Open items not yet closed:
- Materialize real default theme files under `content/themes/default/template/` (not metadata only).
- Implement runtime plugin loading/activation flow (metadata listing alone is insufficient).
- Complete built-in plugin package skeletons (`include.php` + provider registration) and fill missing planned preinstalled plugins.
- Run final clean-workspace 1.0 release freeze verification and release sign-off.

## Step 21 - Workspace Install And Manual Smoke

Status: Completed (2026-06-13)

Goal: validate installer and runtime behavior directly in current workspace (not temp copy).

Scope:
- Run `/install/` in current workspace and complete admin initialization.
- Manually verify: admin login, publish one post, upload one file, one API read, one comment submit, homepage/theme fallback rendering.
- Record observed URLs and key headers in a short run log.

Acceptance:
- Core user flow works in the actual workspace without script isolation.
- No blocking runtime errors in install/admin/frontend/API paths.

Execution result (2026-06-13):
- Result: `WS_SMOKE_PASS`
- Base URL: `http://127.0.0.1:19981`
- Created post id/title: `1` / `Workspace Smoke ws-smoke-1781292122-17672`
- Comment API status: `202`
- Header sample (home): `Cache-Control: no-cache, private`
- Header sample (api): `Cache-Control: no-store, no-cache, must-revalidate`
- Header sample (api): `X-API-Version: v1`
- Header sample (api): `X-Content-Type-Options: nosniff`
- Workspace install artifacts were created during smoke and removed in follow-up cleanup; current workspace is back to uninstalled state.
- Detailed log: `install/WORKSPACE_SMOKE_LOG_20260613.md`

## Step 22 - PROJECT_PLAN Lint Reconciliation

Status: Completed (2026-06-13)

Goal: clean markdown diagnostics in planning docs without changing technical decisions.

Scope:
- Fix heading/list/table/fence formatting in `PROJECT_PLAN.md` (MD022/MD032/MD060/MD031/MD040 classes).
- Keep original semantics and section structure.

Acceptance:
- `PROJECT_PLAN.md` no longer reports blocking markdown formatting diagnostics.
- Content meaning remains unchanged.

Execution result (2026-06-13):
- `PROJECT_PLAN.md` diagnostics: `No errors found`.
- Fixes applied: heading/list/table/fence formatting normalization plus numbering and pseudo-heading cleanup.

## Step 23 - Release Handoff Note

Status: Completed (2026-06-13)

Goal: leave an operator-ready closure note for repeatable release verification.

Scope:
- Summarize exact verification commands (lint + `install/stage20_verify.sh`).
- State required runtime prerequisites and known out-of-scope diagnostics.
- Add rollback/cleanup reminders for install lock and local test DBs.

Acceptance:
- New maintainers can execute the same validation path with minimal context.

Execution result (2026-06-13):
- Added release handoff note: `install/RELEASE_HANDOFF_20260613.md`.
- Verified command evidence: `PHP_LINT_PASS`.
- Verified command evidence: `STAGE20_HTTP_VERIFY_PASS post_id=1 token_prefix=2435973d`.

## Step 24 - Default Theme Materialization

Status: Completed (2026-06-13)

Goal: make `content/themes/default` an actual runnable theme rather than metadata-only.

Scope:
- Add `content/themes/default/template/` with at least `header.php`, `footer.php`, `index.php`, `single.php`, `page.php`, `sidebar.php`.
- Add minimal theme assets directory and reference paths.
- Ensure theme switch to `default` renders from content theme files first, not only `system/Theme` fallback.

Acceptance:
- With `active_theme=default`, frontend pages are rendered by `content/themes/default/template/*` files.
- Removing `system/Theme` fallback files should no longer be required for normal rendering.

Execution result (2026-06-13):
- Materialized theme runtime files:
	- `content/themes/default/template/header.php`
	- `content/themes/default/template/footer.php`
	- `content/themes/default/template/index.php`
	- `content/themes/default/template/single.php`
	- `content/themes/default/template/page.php`
	- `content/themes/default/template/sidebar.php`
	- `content/themes/default/assets/css/theme.css`
- Added explicit runtime marker `data-theme-source="content-default"` to theme layout for source auditing.
- Isolated install audit passed: `STEP24_AUDIT_PASS`.

## Step 25 - Plugin Runtime Loader And Activation

Status: Completed (2026-06-13)

Goal: move from plugin metadata discovery to executable plugin runtime loading.

Scope:
- Add plugin bootstrap/loader in app startup (`content/plugins/{id}/include.php` loading strategy).
- Add activation state persistence and safe loading order.
- Add runtime error isolation so a broken plugin does not fatal the whole site.

Acceptance:
- Enabled plugins are loaded automatically during app bootstrap.
- Disabled plugins are not executed.
- One faulty plugin reports error but does not break full request lifecycle.

Execution result (2026-06-13):
- Added plugin runtime loader and activation state support:
	- App bootstrap now loads plugins via `PluginService::loadEnabled()`.
	- `plugin_setting` enabled flag is now used for runtime activation.
	- Loader captures plugin runtime errors without fatally terminating requests.
- Added plugin status management UI action:
	- `/admin/extensions/plugin` toggle route and `ExtensionAdmin::togglePlugin`.
- Isolated audit passed:
	- Plugin toggle persistence + runtime state check: `STEP25_AUDIT_PASS`.
	- Faulty plugin isolation verified (error captured, homepage still 200).

## Step 26 - Built-In Plugin Packages Completion

Status: Completed (2026-06-13)

Goal: complete built-in plugin packages beyond JSON metadata.

Scope:
- For existing preinstalled plugin dirs (`tabler`, `quill`, `search-like`, `notify-email`, `notify-sms`, `captcha-gd`), add minimal `include.php` and provider registration hook implementation.
- Add missing planned preinstalled plugin dirs/metadata (`social-github`, `ai-openai`) with minimal runnable skeletons.
- Ensure provider lookup path uses plugin registration before fallback behavior.

Acceptance:
- Built-in plugins can be loaded by runtime loader and expose expected provider capabilities.
- Missing provider falls back gracefully as designed in service facades.

Execution result (2026-06-13):
- Completed built-in plugin runtime packages with `include.php` provider hook registration:
	- `tabler`, `quill`, `search-like`, `notify-email`, `notify-sms`, `captcha-gd`.
- Added missing planned preinstalled plugins with runnable skeletons:
	- `social-github` (`plugin.json`, `include.php`)
	- `ai-openai` (`plugin.json`, `include.php`)
- Added provider runtime bridge and service dispatch integration:
	- `system/Service/ProviderRuntime.php`
	- provider lookup + graceful fallback wired in Mail/Sms/Captcha/Search/Ai/Social services.
- Isolated audit passed:
	- runtime package load + provider behavior + missing-provider fallback: `STEP26_AUDIT_PASS`.

## Step 27 - 1.0.0 Release Freeze And Sign-Off

Status: Completed (2026-06-13)

Goal: perform final release-level closure after Steps 24-26 are complete.

Scope:
- Run clean-workspace install + full smoke + Stage20 verifier + php lint.
- Update release handoff notes with final evidence and immutable 1.0.0 checklist.
- Produce release summary and freeze decision log.

Acceptance:
- All release gates pass from a clean workspace.
- No open blockers on default theme/plugin runtime behavior.

Execution result (2026-06-13):
- Final release gates passed in sequence:
	- `PHP_LINT_PASS`
	- `STAGE20_HTTP_VERIFY_PASS post_id=1 token_prefix=00534ff0`
	- `WS_SMOKE_STEP27_PASS post_id=1 token_prefix=2005802c comment_status=202`
- Step27 workspace smoke evidence log:
	- `install/WORKSPACE_SMOKE_LOG_20260613_STEP27.md`
- Post-run cleanup completed and workspace returned to uninstalled baseline:
	- `config.php` removed
	- `storage/data/install.lock` removed
	- `storage/data/finch_workspace_step27.sqlite` removed
