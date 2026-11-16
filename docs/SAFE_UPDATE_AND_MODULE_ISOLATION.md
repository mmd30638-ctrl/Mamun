# Module isolation, error protection and safe updates

## Audit result

The project already had useful safety foundations. These were retained:

- `includes/error_monitor.php` wrote PHP failures to a private log.
- Financial handlers in sales, purchases, returns, salary, account and loan flows commonly use MySQL transactions and rollback.
- `includes/integrity_functions.php` provides named locks for high-risk balance work.
- CSRF, permission checks, login/session enforcement and activity/audit logs are shared centrally.
- `includes/easy_update_functions.php` protected `.env`, `uploads/` and `logs/`.
- `includes/update_backup_functions.php` captured changed files and a database dump before Easy Update.
- Easy Update already exposed manual code/database rollback snapshots.
- `.env.staging`, staging host detection, system health, nightly backup support and schema sync existed.

The audit also found that these controls were incomplete:

- A fatal error after rendering the header could leave a partial or blank page without an Error ID.
- API/action failures could receive HTML instead of a stable JSON error envelope.
- There was no browser-level JavaScript error boundary or client error reporting.
- There was no module-level circuit breaker.
- Easy Update copied directly over live files. A failure halfway through could leave mixed versions.
- Update archives were not syntax-preflighted, concurrent updates were not locked, and `PATCH_DELETE.txt` was not applied safely.
- A failed database dump could still leave a snapshot that looked successful.
- Back navigation depended on `document.referrer`; privacy settings or direct navigation therefore sent users to Home.

## Implemented protection

### Central backend error boundary

`includes/error_monitor.php` is the single central uncaught exception/fatal handler.

- Generates an `ERR-...` identifier.
- Writes structured JSONL plus the PHP error log.
- Returns a stable JSON response for APIs/actions.
- Returns a white, responsive recovery page for browser requests.
- Gives Retry, Back and Home actions.
- Removes a half-rendered buffered page before showing the recovery screen.

This contains page/request failures. It cannot keep the application operating when the PHP runtime, web server, authentication core or database server itself is unavailable.

### Frontend error boundary

`assets/js/error-boundary.js` catches JavaScript exceptions, unhandled promise rejections and critical script/style load failures.

- Shows a compact recovery panel without replacing valid page data.
- Sends a rate-limited structured event to `client-error.php`.
- Does not interrupt the page for an ordinary missing image.

### Module circuit breakers

`module_controls` stores the enabled state and maintenance reason for independent business modules. Admins control it from:

`Settings → Module protection`

When disabled:

- Browser pages receive a clear module-only maintenance page.
- APIs receive HTTP 503 JSON.
- Other modules remain available.
- Admins retain access for diagnosis.

Authentication, database connection and core recovery pages are deliberately not disable-able because they are shared dependencies.

### Safe Update pipeline

Easy Update now follows this order:

1. Validate ZIP signature and reject traversal paths.
2. Extract to a temporary directory.
3. Detect the application root.
4. Parse every included PHP file with `TOKEN_PARSE`.
5. Validate a release version and required `includes/config.php`.
6. Acquire a global non-blocking update lock.
7. Put other requests on a short retry/maintenance page.
8. Back up every file that will change or be deleted.
9. Require a complete database dump; abort if it is unavailable.
10. Stage each new file beside its destination and atomically rename it.
11. Apply only safe file deletions listed in `PATCH_DELETE.txt`.
12. Automatically restore the file snapshot if any apply step fails.
13. Invalidate OPcache and release the lock.

`.env`, `includes/db.php`, `uploads/` and `logs/` remain protected.

Top-level, Filebin nested and rollback ZIP files all reject traversal paths,
absolute paths and symlink entries before extraction. Full-stack ZIP generation
and the legacy tools updater require an authenticated admin session.

### Financial retry safety

Sale, purchase/create/edit, customer payment/create/edit/withdrawal, sale and
purchase returns, salary, account receive/manual entry/transfer and legacy
partner-capital entries claim their idempotency token inside the same InnoDB
transaction as the money movement. A rollback therefore removes the unfinished
claim automatically; a successful reference and its token commit together.
Row or named locks additionally serialize payment edits, returns and transfers.
This prevents both double posting and the old failure mode where a failed
request permanently blocked a legitimate retry.

### Back navigation

The shared navigation script keeps the last 40 safe internal GET pages in `sessionStorage`.

- Back now visits the previous application page one step at a time.
- Action/process endpoints and external URLs are never stored.
- Legacy Back buttons that incorrectly target Home are upgraded when safe.
- Explicit Home and Dashboard buttons remain Home/Dashboard actions.

## Database changes

All changes are additive and idempotent:

- `module_controls`
- `partner_v2_settings`
- `partner_v2_product_rules`
- `partner_v2_direct_costs`
- `partner_v2_expenses`
- `partner_v2_movements`
- `partner_v2_months`
- `partner_v2_audit`

Canonical definitions live in code and in:

`migrations/003_safe_module_partner_v2.sql`

No legacy partner, sales, purchase, stock, customer, supplier, cash or bank rows are deleted.

## Required update workflow

1. Commit and push a version-control checkpoint.
2. Build an additive patch; never include `.env` or `uploads/`.
3. Run all PHP and JavaScript syntax checks.
4. Run `tests/safety_architecture_test.php`.
5. Run `tests/partner_accounting_v2_test.php` against a disposable database.
6. Run `tests/money_idempotency_test.php` and `tests/legacy_module_regression_test.php`.
7. Test login, dashboard, sales, purchase, inventory, customer, supplier, HRM, accounts and reports on staging.
8. Test phone, tablet and desktop layouts.
9. Apply with Easy Update so preflight, lock, backup and automatic rollback run.
10. Confirm `/v.php`, system health, schema sync and error diagnostics.
11. If code fails, restore one of the five retained update checkpoints. Restore DB only when that release actually changed schema/data and the consequences have been reviewed.

## Recovery

- Code-only failure: Easy Update snapshot → restore without DB.
- Additive schema issue: fix forward when possible.
- Destructive/data migration issue: stop writes, download the current DB, then restore the matching pre-update DB snapshot.
- Shared database outage: use hosting/database recovery; module isolation cannot bypass it.
- Error investigation: search Error ID in Settings → Error diagnostics and private logs.

## Known limits

- Multi-file deployment is composed of atomic per-file replacements; filesystems do not provide a transaction across the whole tree. The update lock plus automatic snapshot restoration provides recovery.
- MySQL DDL can implicitly commit. A database restore containing schema DDL is not fully transactional; this is why migrations must be additive and a physical SQL backup is mandatory.
- Existing legacy endpoints that deliberately call `die()` with plain text keep that behavior. Uncaught failures use the centralized JSON/HTML boundary.
- True high availability against web-server, PHP-FPM or database-server failure requires hosting-level redundancy and monitoring outside this repository.
