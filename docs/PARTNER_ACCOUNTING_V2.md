# Partner accounting v2

## Scope and cutoff

The v2 ledger starts once, at the first day of the month in which it is initialized. The date is stored in `partner_v2_settings.cutoff_date`.

- Legacy partner reports remain unchanged and act as an archive.
- Sales and partner transactions before the cutoff never enter v2.
- Core sales, purchase, stock, customer, supplier, cash and bank ledgers are read-only inputs; v2 does not rewrite them.

## Product profit

Only products with an effective `partner_v2_product_rules` row participate.

For each eligible sale/product:

1. Gross line sales = quantity × sale price.
2. Header discount is allocated proportionally by gross line sales.
3. COGS uses the frozen `sale_items.purchase_price`.
4. Approved direct costs are deducted once.
5. Approved/completed returns reduce net sales and their corresponding frozen COGS.
6. Remaining product profit is split by the effective partner ratio.

Rules cannot overlap for the same product. Default UI values are 50:50, but an authorized user can set any two positive ratios totaling 100%.

Returns in a later open month create a negative adjustment in that return month. Returns tied to pre-cutoff sales are excluded because their original profit was never admitted to v2.

## Joint expenses

`partner_v2_expenses` stores voucher, category, description, payment method, payer, receipt, approval and void history.

- Business-paid expense reduces joint profit once.
- Partner-paid expense also reduces joint profit once and separately creates an equal reimbursable receivable for that partner.
- Voiding preserves the original row and audit history.

## Partner movements

`partner_v2_movements` keeps these separate:

- Capital
- Additional investment
- Personal withdrawal
- Profit payment
- Expense reimbursement
- Partner loan to the business

Investment is never profit. Withdrawal is never business expense. Unique references prevent duplicate posting.

## Month close

Open months calculate live. Closing a month stores the complete report as JSON and a SHA-256 hash in `partner_v2_months`.

- Closed reports use the snapshot and do not change after later sale edits.
- Normal entries are blocked in a closed month.
- Only an admin can explicitly reopen; reopening is recorded in `partner_v2_audit`.

## Reports and exports

The dashboard provides:

- Eligible product sales and COGS
- Product and invoice profit
- Partner gross and net profit
- Joint expenses
- Capital, investment, withdrawal and payments
- Reimbursable partner expenses and receivables
- Date, product, partner and expense-category filters
- UTF-8 CSV for Excel
- Browser print/PDF

## Files

- `includes/partner_accounting_v2.php` — schema and calculation service
- `modules/partners/accounting/index.php` — responsive white dashboard
- `modules/partners/accounting/action.php` — transactional commands
- `modules/partners/accounting/export.php` — CSV export
- `tests/partner_accounting_v2_test.php` — accounting invariants
- `migrations/003_safe_module_partner_v2.sql` — additive SQL reference
