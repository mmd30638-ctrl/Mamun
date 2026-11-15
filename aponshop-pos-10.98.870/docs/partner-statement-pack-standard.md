# Partner statement pack (intl-100)

**Version:** `PA_STMT_PACK_VER` in `includes/partner_statement_pack.php` (10.98.936+)

**Pagination:** `PA_STMT_ROWS_PER_PAGE = 15` — each list page shows up to 15 numbered rows (`pg` query); totals unchanged across pages.

## Purpose

Transparent partner monthly reporting in four read-only views that **reconcile to one ledger book** (`pa_partner_book()`).

| View | File | Source |
|------|------|--------|
| Profit detail | `modules/accounts/partner_accounting_profit.php` | `pa_ledger_sale_lines` + `pa_split_rows` → `book['plus']` |
| Outflow (expense + withdrawal + other minus) | `modules/accounts/partner_accounting_outflow.php` | `book['lines']` where `minus > 0` |
| Summary | `modules/accounts/partner_accounting_summary.php` | `book` opening/plus/minus/total |
| Full ledger | `modules/accounts/partner_accounting_statement.php` | `pa_book_apply` lines with `previous` + `running` |

## Invariants (review checklist)

1. **No writes on statement load** — display only.
2. **Outflow page total** === `book['minus']` (see `reconciled` flag).
3. **Profit page total** === `book['plus']` after `pa_split_rows` rounding.
4. **Summary formula:** `opening + plus − minus = total` === ledger footer.
5. Mobile + Chrome desktop-site: `partner-accounting.css` (`max-width: 1100px` and `pointer: coarse`).
6. Partner required: `?month=YYYY-MM&partner_id=N`.

## Deploy

Easy Update ZIP under `deliverables/`; **do not** restore DB for file-only updates. Bump `AME_ASSET_VERSION` with each release.